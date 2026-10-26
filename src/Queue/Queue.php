<?php

declare(strict_types=1);

namespace MajorDoMo\Queue;

use Throwable;

/**
 * Очередь заданий MyDoMo.
 *
 * Реализации не обязаны использовать одну таблицу; ядро и модули зависят
 * только от этого контракта.
 */
interface Queue
{
    /**
     * @param array<string, mixed> $payload
     * @return int id задания
     */
    public function push(string $queue, string $job, array $payload = [], int $delaySeconds = 0): int;

    public function pop(string $queue): ?QueueJob;

    public function ack(QueueJob $job): void;

    public function fail(QueueJob $job, Throwable $error): void;
}
