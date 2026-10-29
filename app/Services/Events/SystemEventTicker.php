<?php

declare(strict_types=1);

namespace LaIOT\Services\Events;

use Carbon\CarbonImmutable;

/**
 * Отслеживает границы календарного времени без воспроизведения пропусков.
 *
 * 2026-10-27 — события минуты, часа и дня в часовом поясе приложения.
 */
final class SystemEventTicker
{
    private ?int $minute = null;

    public function __construct(private readonly SystemEvents $events)
    {
    }

    public function tick(CarbonImmutable $now): void
    {
        $minute = intdiv($now->getTimestamp(), 60);
        if ($this->minute === null) {
            $this->minute = $minute;

            return;
        }
        if ($this->minute === $minute) {
            return;
        }
        $this->minute = $minute;
        $data = ['occurred_at' => $now->startOfMinute()->toIso8601String()];
        $this->events->emit('system.minute', $data);
        if ((int)$now->format('i') === 0) {
            $this->events->emit('system.hour', $data);
            if ((int)$now->format('G') === 0) {
                $this->events->emit('system.day', $data);
            }
        }
    }
}
