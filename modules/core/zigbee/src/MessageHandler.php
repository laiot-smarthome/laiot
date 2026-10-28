<?php

declare(strict_types=1);

namespace MajorDoMo\Zigbee;

use MajorDoMo\Queue\JobHandler;

/**
 * Задание очереди `module.zigbee` с одним сообщением брокера.
 *
 * Задание создаёт MQTT-цикл, исполняет `core-worker module.zigbee` или
 * `worker zigbee` (оба обслуживают эту очередь). Ошибка разбора возвращается
 * в очередь: значение свойств уже могло записаться, поэтому обработчик обязан
 * быть идемпотентным.
 *
 * 2026-10-28 — перенос маршрута alpha `moduleevent` на задание SDK.
 */
final readonly class MessageHandler implements JobHandler
{
    public function __construct(private Service $service)
    {
    }

    public function handle(array $payload): void
    {
        $topic = trim((string)($payload['topic'] ?? ''));
        if ($topic === '') {
            return;
        }
        $this->service->receive($topic, MqttIntegration::decodePayload($payload));
    }
}
