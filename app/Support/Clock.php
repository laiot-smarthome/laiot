<?php

declare(strict_types=1);

namespace LaIOT\Support;

use Carbon\CarbonImmutable;

/**
 * Единый источник времени LaIOT.
 *
 * Все новые таблицы используют `created_at/updated_at/deleted_at`, а код —
 * этот Clock, а не `date()`/`time()`. В тестах время можно заморозить через
 * CarbonImmutable::setTestNow().
 */
final readonly class Clock
{
    public function __construct(private string $timezone = 'Europe/Moscow')
    {
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone);
    }

    public function nowString(): string
    {
        return $this->now()->format('Y-m-d H:i:s');
    }

    public function timezone(): string
    {
        return $this->timezone;
    }
}
