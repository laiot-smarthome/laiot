<?php

declare(strict_types=1);

namespace LaIOT\Mqtt;

/**
 * Отправка команды в брокер.
 *
 * Отдельный интерфейс нужен тестам и будущим транспортам: подписчик
 * свойства не зависит от конкретного клиента и его соединения.
 */
interface PublisherInterface
{
    public function publish(string $topic, string $message, int $qos = 0, bool $retain = false): void;
}
