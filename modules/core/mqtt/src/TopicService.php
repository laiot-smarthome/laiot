<?php

declare(strict_types=1);

namespace LaIOT\Mqtt;

use InvalidArgumentException;
use LaIOT\Services\Locations\LocationRepository;
use LaIOT\Services\Methods\MethodRepository;
use LaIOT\Services\Modules\ModuleSubscriptions;
use LaIOT\Services\Objects\ObjectRepository;
use LaIOT\Services\Properties\PropertyRepository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Правила редактирования привязок MQTT.
 *
 * Контроллер не пишет в таблицу напрямую: здесь проверяются имена, дубли
 * путей, объект/свойство/метод и синхронизация `property_subscriptions`.
 * Подписку на свойство ставит сам модуль — пользователь в карточке топика
 * выбирает объект, а ядро доставляет изменения в очередь `module.mqtt`.
 *
 * 2026-10-27 — PRG-формы топика вместо alpha `edit_mqtt`.
 */
final class TopicService
{
    public function __construct(
        private readonly TopicRepository $topics,
        private readonly BindingIndex $bindings,
        private readonly ModuleSubscriptions $subscriptions,
        private readonly Schema $schema,
        private readonly ObjectRepository $objects,
        private readonly PropertyRepository $properties,
        private readonly MethodRepository $methods,
        private readonly LocationRepository $locations,
        private readonly PublisherInterface $publisher,
        private readonly Broker $broker,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function save(array $input, int $id = 0): array
    {
        $this->schema->ensure();
        $previous = $id > 0 ? $this->topics->find($id) : null;
        if ($id > 0 && $previous === null) {
            throw new InvalidArgumentException('Топик не найден.');
        }

        $path = trim((string)($input['path'] ?? ''));
        if ($path === '') {
            throw new InvalidArgumentException('Укажите путь топика.');
        }
        if (str_contains($path, '+') || str_contains($path, '#')) {
            throw new InvalidArgumentException('Путь топика не может содержать подстановочные знаки + и #.');
        }
        if (mb_strlen($path, 'UTF-8') > 255) {
            throw new InvalidArgumentException('Путь топика длиннее 255 символов.');
        }
        $duplicate = $this->topics->findByPath($path);
        if ($duplicate !== null && (int)$duplicate['id'] !== $id) {
            throw new InvalidArgumentException('Такой топик уже есть: «' . $path . '».');
        }

        $pathWrite = trim((string)($input['path_write'] ?? ''));
        if (str_contains($pathWrite, '+') || str_contains($pathWrite, '#')) {
            throw new InvalidArgumentException('Топик команды не может содержать подстановочные знаки + и #.');
        }
        if ($pathWrite === $path) {
            $pathWrite = '';
        }

        $objectName = trim((string)($input['linked_object'] ?? ''));
        $propertyName = trim((string)($input['linked_property'] ?? ''));
        $methodName = trim((string)($input['linked_method'] ?? ''));
        $objectId = null;
        $propertyId = null;
        if ($objectName === '') {
            $propertyName = '';
            $methodName = '';
        } else {
            $object = $this->objects->findByTitle($objectName);
            if ($object === null) {
                throw new InvalidArgumentException('Объект не найден: «' . $objectName . '».');
            }
            $objectId = (int)$object['id'];
            if ($propertyName !== '') {
                $property = $this->properties->resolveForObject($objectId, $propertyName);
                if ($property === null) {
                    throw new InvalidArgumentException('Свойство «' . $propertyName . '» не действует у объекта «' . $objectName . '».');
                }
                $propertyId = (int)$property['id'];
            }
            if ($methodName !== '') {
                $method = $this->methods->findForObject($objectId, (int)$object['class_id'], $methodName);
                if ($method === null) {
                    throw new InvalidArgumentException('Метод «' . $methodName . '» не найден у объекта «' . $objectName . '».');
                }
            }
            if ($propertyName === '' && $methodName === '') {
                throw new InvalidArgumentException('Выберите свойство или метод объекта либо очистите привязку.');
            }
        }

        $title = trim((string)($input['title'] ?? ''));
        if ($title === '') {
            $title = self::lastSegment($path);
        }

        $locationId = (int)($input['location_id'] ?? 0);
        if ($locationId > 0 && $this->locations->find($locationId) === null) {
            $locationId = 0;
        }

        $saved = $this->topics->save([
            'id' => $id,
            'path' => $path,
            'title' => mb_substr($title, 0, 255),
            'path_write' => $pathWrite,
            'linked_object' => $objectName,
            'linked_property' => $propertyName,
            'linked_method' => $methodName,
            'linked_object_id' => $objectId,
            'linked_property_id' => $propertyId,
            'location_id' => $locationId > 0 ? $locationId : null,
            'logging' => !empty($input['logging']),
            'only_new_value' => !empty($input['only_new_value']),
            'readonly' => !empty($input['readonly']),
            'replace_list' => mb_substr(trim((string)($input['replace_list'] ?? '')), 0, 255),
            'qos' => max(0, min(2, (int)($input['qos'] ?? 0))),
            'retain' => !empty($input['retain']),
        ]);
        $id = $saved;
        $this->bindings->invalidate();

        $oldObject = (string)($previous['linked_object'] ?? '');
        $oldPropertyId = (int)($previous['linked_property_id'] ?? 0);
        if ($oldObject !== '' && $oldPropertyId > 0
            && ($oldObject !== $objectName || $oldPropertyId !== $propertyId)
            && $this->topics->countBindingsFor($oldObject, $oldPropertyId, $id) === 0) {
            try {
                $this->subscriptions->detach((int)($previous['linked_object_id'] ?? 0), $oldPropertyId);
            } catch (Throwable $error) {
                $this->logger->warning('MQTT: старая подписка свойства не снята', [
                    'error' => $error->getMessage(),
                ]);
            }
        }

        if ($objectId !== null && $propertyId !== null && $propertyId > 0) {
            $this->subscriptions->attach($objectId, $propertyId);
        }

        return $this->topics->find($id) ?? [];
    }

    public function delete(int $id): void
    {
        $topic = $this->topics->find($id);
        if ($topic === null) {
            return;
        }
        $this->topics->delete($id);
        $this->bindings->invalidate();
        $objectId = (int)($topic['linked_object_id'] ?? 0);
        $propertyId = (int)($topic['linked_property_id'] ?? 0);
        if ($objectId > 0 && $propertyId > 0
            && $this->topics->countBindingsFor((string)$topic['linked_object'], $propertyId) === 0) {
            try {
                $this->subscriptions->detach($objectId, $propertyId);
            } catch (Throwable $error) {
                $this->logger->warning('MQTT: подписка свойства не снята при удалении', [
                    'error' => $error->getMessage(),
                ]);
            }
        }
    }

    public function publishTest(int $id, string $value): void
    {
        $topic = $this->topics->find($id);
        if ($topic === null) {
            throw new InvalidArgumentException('Топик не найден.');
        }
        $path = (string)$topic['path_write'] !== '' ? (string)$topic['path_write'] : (string)$topic['path'];
        $this->publish($path, $value, (int)$topic['qos'], (bool)$topic['retain']);
        $this->topics->updateValue($id, $value);
        if (!empty($topic['logging'])) {
            $this->topics->addHistory([[
                'topic_id' => $id,
                'direction' => TopicRepository::DIRECTION_OUT,
                'topic' => $path,
                'value' => mb_strcut($value, 0, 65535, 'UTF-8'),
                'payload' => mb_strcut($value, 0, 65535, 'UTF-8'),
                'linked_data' => '',
                'created_at' => date('Y-m-d H:i:s'),
            ]]);
        }
    }

    public function publish(string $topic, string $value, int $qos = 0, bool $retain = false): void
    {
        $this->publisher->publish($topic, $value, $qos, $retain);
        $this->setLastValueByPath($topic, $value);
    }

    /**
     * Проверка соединения по текущим сохранённым настройкам.
     */
    public function testConnection(): ?string
    {
        try {
            $this->broker->connect();
            $this->broker->close();

            return null;
        } catch (Throwable $error) {
            return $error->getMessage();
        }
    }

    private function setLastValueByPath(string $path, string $value): void
    {
        $topic = $this->topics->findByPath($path);
        if ($topic === null) {
            return;
        }
        try {
            $this->topics->updateValue((int)$topic['id'], $value);
        } catch (Throwable $error) {
            $this->logger->warning('MQTT: последнее значение ручной команды не сохранено', [
                'error' => $error->getMessage(),
            ]);
        }
    }

    public static function lastSegment(string $path): string
    {
        $parts = preg_split('#/+#', trim($path, '/')) ?: [];
        $last = end($parts);

        return $last === false || $last === '' ? trim($path) : (string)$last;
    }
}
