<?php

declare(strict_types=1);

namespace MajorDoMo\Queue;

/**
 * Задание, выданное очереди.
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
        public int $attempts
    ) {
    }
}
