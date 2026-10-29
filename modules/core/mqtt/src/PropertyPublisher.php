<?php

declare(strict_types=1);

namespace LaIOT\Mqtt;

use LaIOT\Services\Events\PropertyChange;
use LaIOT\Services\Modules\PropertySubscriber;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Изменение свойства объекта → команда MQTT.
 *
 * Подписки на свойства выбирает пользователь: при сохранении привязки
 * топика модуль сам ставит её через `$context->subscriptions()`. Core
 * доставляет изменение в очередь `module.<id>`, поэтому запись значения не
 * ждёт брокера. Собственное эхо входящего сообщения не публикуется обратно.
 *
 * 2026-10-27 — обработчик свойства для MQTT-модуля.
 */
final class PropertyPublisher implements PropertySubscriber
{
    public function __construct(
        private readonly BindingIndex $bindings,
        private readonly TopicRepository $topics,
        private readonly PublisherInterface $publisher,
        private readonly Settings $settings,
        private readonly RuntimeState $runtime,
        private readonly LoggerInterface $logger
    ) {
    }

    public function onPropertyChanged(PropertyChange $change): void
    {
        // Значение пришло из MQTT: публиковать его назад нельзя.
        if (str_starts_with($change->source, 'mqtt')) {
            return;
        }

        $this->bindings->ensureFresh(1.0);
        $rows = $this->bindings->bindingsFor($change->objectId, $change->propertyId);
        if ($rows === []) {
            return;
        }

        $value = $change->newValue;
        foreach ($rows as $row) {
            if (!empty($row['readonly'])) {
                continue;
            }
            $topic = (string)$row['path_write'] !== '' ? (string)$row['path_write'] : (string)$row['path'];
            if ($topic === '') {
                continue;
            }

            $payload = $this->mapValue($value, (string)$row['replace_list']);
            $started = microtime(true);
            try {
                $this->publisher->publish($topic, $payload, (int)$row['qos'], (bool)$row['retain']);
            } catch (Throwable $error) {
                $this->runtime->error('MQTT publish ' . $topic . ': ' . $error->getMessage());
                $this->logger->warning('MQTT: команда не отправлена', [
                    'topic' => $topic,
                    'error' => $error->getMessage(),
                ]);
                throw $error;
            }

            $updated = date('Y-m-d H:i:s');
            try {
                $this->topics->updateValue((int)$row['id'], $payload, $updated);
            } catch (Throwable $error) {
                // Команда уже ушла: не заставляем очередь повторять публикацию
                // из-за сбоя проекции последнего значения.
                $this->logger->warning('MQTT: не сохранено последнее значение топика', [
                    'topic' => $topic,
                    'error' => $error->getMessage(),
                ]);
            }

            if (!empty($row['logging']) || $this->settings->bool('debug')) {
                $this->writeHistory($row, $topic, $payload, $updated);
            }

            $this->runtime->published((microtime(true) - $started) * 1000);
            if ($this->settings->bool('debug')) {
                $this->logger->info('MQTT publish', [
                    'topic' => $topic,
                    'value' => $payload,
                    'source' => $change->token(),
                ]);
            }
        }
    }

    /**
     * Список замен пишется парами `новое=старое`: наружу значение меняется
     * в обратную сторону, чем при приёме.
     */
    private function mapValue(string $value, string $list): string
    {
        $list = trim($list);
        if ($list === '') {
            return $value;
        }
        foreach (explode(',', $list) as $pair) {
            $pair = trim($pair);
            if ($pair === '' || !str_contains($pair, '=')) {
                continue;
            }
            [$new, $old] = explode('=', $pair, 2);
            if ($value == trim($old)) {
                return trim($new);
            }
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function writeHistory(array $row, string $topic, string $payload, string $updated): void
    {
        $linked = '';
        if ((string)$row['linked_object'] !== '' && (string)$row['linked_property'] !== '') {
            $linked = $row['linked_object'] . '.' . $row['linked_property'];
        }
        try {
            $this->topics->addHistory([[
                'topic_id' => (int)$row['id'],
                'direction' => TopicRepository::DIRECTION_OUT,
                'topic' => $topic,
                'value' => mb_strcut($payload, 0, 65535, 'UTF-8'),
                'payload' => mb_strcut($payload, 0, 65535, 'UTF-8'),
                'linked_data' => mb_strcut($linked, 0, 255, 'UTF-8'),
                'created_at' => $updated,
            ]]);
        } catch (Throwable $error) {
            $this->logger->warning('MQTT: история команды не записана', [
                'error' => $error->getMessage(),
            ]);
        }
    }
}
