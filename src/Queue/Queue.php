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

    /**
     * @return array{pending:int,reserved:int,failed:int}
     */
    public function stats(string $queue): array;

    /**
     * @return list<array{id:int,queue:string,job:string,attempts:int,error:string,failed_at:string}>
     */
    public function failed(string $queue, int $limit = 50): array;

    /**
     * Вернуть failed-задание в очередь с нулевыми попытками.
     */
    public function retry(int $id): bool;

    /**
     * Удалить failed-задание окончательно.
     */
    public function forget(int $id): bool;
}
