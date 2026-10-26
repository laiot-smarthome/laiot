<?php

declare(strict_types=1);

namespace MajorDoMo\Properties;

use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Events\PropertyEventChain;
use MajorDoMo\Events\PropertyEventRepository;
use MajorDoMo\History\HistoryRepository;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Methods\MethodRunner;
use MajorDoMo\Objects\ObjectRepository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Единственный путь записи значения свойства в новом ядре.
 *
 * Цепочка: запись значения → история (если включена) → строка журнала →
 * OnChange-метод свойства. Метод выполняется синхронно в текущем процессе:
 * фоновые циклы и очередь для этого не нужны. Ошибка пользовательского кода
 * не откатывает значение — она попадает в журнал `property_events` и в лог.
 *
 * 2026-10-26 — запись по ID проверяет принадлежность свойства классу объекта.
 * 2026-10-26 — сервис создан для нового ядра MyDoMo: единая запись вместо
 *              `ValueRepository::updateValue()` и `sg()` в обход истории.
 */
final class PropertyValueService
{
    private readonly PropertyEventChain $chain;
    private int $parentEventId = 0;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly ObjectRepository $objects,
        private readonly PropertyRepository $properties,
        private readonly ValueRepository $values,
        private readonly HistoryRepository $history,
        private readonly MethodRepository $methods,
        private readonly MethodRunner $methodRunner,
        private readonly PropertyEventRepository $events,
        private readonly LoggerInterface $logger
    ) {
        $this->chain = new PropertyEventChain();
    }

    /**
     * Находит объект и его свойство (в классе объекта, затем глобальное).
     *
     * @return array{
     *     object:array{id:int,title:string,class_id:int,location_id:int,description:string},
     *     property:array{id:int,class_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string}
     * }|null
     */
    public function resolve(string $objectTitle, string $propertyTitle): ?array
    {
        $object = $this->objects->findByTitle(trim($objectTitle));
        if ($object === null) {
            return null;
        }

        $property = $this->properties->resolveForClass((int)$object['class_id'], $propertyTitle);
        if ($property === null) {
            return null;
        }

        return ['object' => $object, 'property' => $property];
    }

    public function get(string $objectTitle, string $propertyTitle): ?string
    {
        $resolved = $this->resolve($objectTitle, $propertyTitle);

        return $resolved === null
            ? null
            : $this->values->get($resolved['object']['id'], $resolved['property']['id']);
    }

    /**
     * @return list<array{value:string,created_at:string}>
     */
    public function history(string $objectTitle, string $propertyTitle, int $limit = 50): array
    {
        $resolved = $this->resolve($objectTitle, $propertyTitle);

        return $resolved === null
            ? []
            : $this->history->forValue($resolved['object']['id'], $resolved['property']['id'], $limit);
    }

    /**
     * `sg()`-совместимая запись по названиям «Объект.Свойство».
     *
     * @param mixed $value Скаляр, null или массив (массив сохраняется как JSON).
     */
    public function set(string $objectTitle, string $propertyTitle, mixed $value, string $source = 'sg'): bool
    {
        $resolved = $this->resolve($objectTitle, $propertyTitle);
        if ($resolved === null) {
            return false;
        }

        return $this->apply($resolved['object'], $resolved['property'], $value, $source);
    }

    /**
     * Запись по идентификаторам: так пишут формы объектов и страница значений.
     */
    public function setValue(int $objectId, int $propertyId, mixed $value, string $source = 'panel'): bool
    {
        $object = $this->objects->find($objectId);
        $property = $this->properties->find($propertyId);
        if ($object === null || $property === null
            || ($property['class_id'] !== 0 && $property['class_id'] !== $object['class_id'])) {
            return false;
        }

        return $this->apply($object, $property, $value, $source);
    }

    /**
     * @param array{id:int,title:string,class_id:int,location_id:int,description:string} $object
     * @param array{id:int,class_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string} $property
     */
    private function apply(array $object, array $property, mixed $value, string $source): bool
    {
        $newValue = $this->normalize($value);
        if ($newValue === null) {
            return false;
        }

        $objectId = (int)$object['id'];
        $propertyId = (int)$property['id'];
        $objectTitle = (string)$object['title'];
        $propertyTitle = (string)$property['title'];
        $source = mb_substr(trim($source), 0, 191);

        $oldValue = (string)($this->values->get($objectId, $propertyId) ?? '');
        $changed = $oldValue !== $newValue;
        $token = $this->chain->token($objectTitle, $propertyTitle);
        $loop = $this->chain->contains($token);
        $tooDeep = $this->chain->depth() >= PropertyEventChain::MAX_DEPTH;
        $stopped = $loop || $tooDeep;
        $depth = $this->chain->depth();
        $chain = $this->nextChain($token);

        // Значение, история и шаг журнала фиксируются одной транзакцией.
        // Пользовательский метод выполняется уже после commit: его ошибка не
        // откатывает сохранённое значение.
        $eventId = $this->database->transaction(function () use (
            $objectId,
            $propertyId,
            $objectTitle,
            $propertyTitle,
            $newValue,
            $oldValue,
            $changed,
            $stopped,
            $loop,
            $source,
            $chain,
            $depth,
            $property
        ): int {
            $this->values->write($objectId, $propertyId, $newValue);
            if ($changed && $property['keep_history']) {
                $this->history->record($objectId, $propertyId, $newValue);
            }
            if (!$changed) {
                return 0;
            }

            return $this->events->record([
                'parent_id' => $this->parentEventId,
                'type' => 'property.changed',
                'status' => $stopped ? 'stopped' : 'changed',
                'source' => $source,
                'object_id' => $objectId,
                'property_id' => $propertyId,
                'object_title' => $objectTitle,
                'property_title' => $propertyTitle,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'message' => $stopped ? $this->stopMessage($loop) : null,
                'chain' => $chain,
                'depth' => $depth,
            ]);
        });

        $handler = trim((string)$property['on_change']);
        if ($handler === '') {
            return true;
        }

        // Обработчик не вызывается, но шаг остаётся видимым: иначе «свойство
        // записалось, а код не сработал» выглядит как потеря события.
        if ($stopped) {
            $this->events->record([
                'parent_id' => $eventId > 0 ? $eventId : $this->parentEventId,
                'type' => 'handler.method',
                'status' => 'skipped',
                'source' => $source,
                'object_id' => $objectId,
                'property_id' => $propertyId,
                'object_title' => $objectTitle,
                'property_title' => $propertyTitle,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'handler' => $handler,
                'message' => $this->stopMessage($loop),
                'chain' => $chain,
                'depth' => $depth + 1,
            ]);

            return true;
        }

        $this->runHandler(
            $object,
            $property,
            $handler,
            $oldValue,
            $newValue,
            $source,
            $token,
            $chain,
            $depth,
            $eventId > 0 ? $eventId : $this->parentEventId
        );

        return true;
    }

    /**
     * @param array{id:int,title:string,class_id:int,location_id:int,description:string} $object
     * @param array{id:int,class_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string} $property
     * @param list<string> $chain
     */
    private function runHandler(
        array $object,
        array $property,
        string $handler,
        string $oldValue,
        string $newValue,
        string $source,
        string $token,
        array $chain,
        int $depth,
        int $parentId
    ): void {
        $objectTitle = (string)$object['title'];
        $propertyTitle = (string)$property['title'];

        // Строка создаётся до запуска кода: вложенные записи должны видеть
        // родителя в журнале, а «зависший» обработчик остаётся заметным.
        $handlerEventId = $this->events->record([
            'parent_id' => $parentId,
            'type' => 'handler.method',
            'status' => 'running',
            'source' => $source,
            'object_id' => (int)$object['id'],
            'property_id' => (int)$property['id'],
            'object_title' => $objectTitle,
            'property_title' => $propertyTitle,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'handler' => $handler,
            'chain' => $chain,
            'depth' => $depth + 1,
        ]);

        $previousParent = $this->parentEventId;
        $this->parentEventId = $handlerEventId;
        $this->chain->push($token);

        try {
            if ($this->methods->findForClass((int)$object['class_id'], $handler) === null) {
                $this->events->finish($handlerEventId, 'missing', 'Метод не найден: ' . $handler);
                $this->logger->warning('OnChange-метод свойства не найден', [
                    'object' => $objectTitle,
                    'property' => $propertyTitle,
                    'handler' => $handler,
                ]);

                return;
            }

            $this->methodRunner->run($objectTitle, $handler, [
                'OBJECT' => $objectTitle,
                'PROPERTY' => $propertyTitle,
                'OLD_VALUE' => $oldValue,
                'NEW_VALUE' => $newValue,
                'SOURCE' => $source,
                'DEPTH' => $depth + 1,
                'CHAIN' => $chain,
            ]);
            $this->events->finish($handlerEventId, 'ok');
        } catch (Throwable $error) {
            $this->events->finish($handlerEventId, 'error', $error->getMessage());
            $this->logger->warning('OnChange-обработчик свойства завершился ошибкой', [
                'object' => $objectTitle,
                'property' => $propertyTitle,
                'handler' => $handler,
                'error' => $error->getMessage(),
            ]);
        } finally {
            $this->parentEventId = $previousParent;
            $this->chain->pop();
        }
    }

    /**
     * Цепочка с текущим свойством: она же попадает в журнал как «где мы».
     *
     * @return list<string>
     */
    private function nextChain(string $token): array
    {
        $chain = $this->chain->tokens();
        if (!in_array($token, $chain, true)) {
            $chain[] = $token;
        }

        return $chain;
    }

    private function stopMessage(bool $loop): string
    {
        return $loop
            ? 'Свойство уже встречалось в цепочке: обработчик не вызван, значение сохранено.'
            : 'Цепочка достигла предела ' . PropertyEventChain::MAX_DEPTH
                . ' шагов: обработчик не вызван, значение сохранено.';
    }

    /**
     * Значение приводится один раз, до записи и журнала.
     */
    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return (string)$value;
        }
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

            return $encoded === false ? null : $encoded;
        }

        return null;
    }
}
