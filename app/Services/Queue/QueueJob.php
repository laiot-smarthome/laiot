<?php

declare(strict_types=1);

namespace LaIOT\Services\Queue;

/**
 * Задание, выданное очереди.
 *
 * 2026-10-27 — идентификатор резервации защищает от устаревшего подтверждения.
 */
final readonly class QueueJob
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $id,
        public string $queue,
        public string $job,
        public array $payload,
        public int $attempts,
        public string $reservationToken
    ) {
    }
}
