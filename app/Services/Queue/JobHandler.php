<?php

declare(strict_types=1);

namespace LaIOT\Services\Queue;

/**
 * Обработчик задания очереди.
 */
interface JobHandler
{
    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload): void;
}
