<?php

declare(strict_types=1);

namespace MajorDoMo\Queue;

use RuntimeException;

/**
 * Реестр именованных обработчиков.
 *
 * Ядро и core-модули регистрируют здесь свои задания; worker не знает
 * конкретных классов и не выполняет произвольные строки.
 */
final class JobRegistry
{
    /** @var array<string, JobHandler|callable> */
    private array $handlers = [];

    public function register(string $job, JobHandler|callable $handler): void
    {
        if (!preg_match('/^[a-z][a-z0-9_.-]{1,190}$/D', $job)) {
            throw new RuntimeException('Некорректное имя задания: ' . $job);
        }

        $this->handlers[$job] = $handler;
    }

    public function has(string $job): bool
    {
        return isset($this->handlers[$job]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(string $job, array $payload): void
    {
        if (!isset($this->handlers[$job])) {
            throw new RuntimeException('Обработчик задания не зарегистрирован: ' . $job);
        }

        $handler = $this->handlers[$job];
        if ($handler instanceof JobHandler) {
            $handler->handle($payload);

            return;
        }

        $handler($payload);
    }
}
