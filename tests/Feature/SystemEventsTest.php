<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Carbon\CarbonImmutable;
use MajorDoMo\Events\SystemEvents;
use MajorDoMo\Events\SystemEventTicker;
use MajorDoMo\Queue\Queue;
use PHPUnit\Framework\TestCase;

/** 2026-10-27 — проверка календарных границ и доставки системных событий. */
final class SystemEventsTest extends TestCase
{
    public function testBoundariesAndSkippedIntervals(): void
    {
        $queue = $this->createMock(Queue::class);
        $jobs = [];
        $queue->method('push')->willReturnCallback(static function (string $queue, string $job, array $payload) use (&$jobs): int {
            $jobs[] = [$queue, $job, $payload];

            return count($jobs);
        });
        $events = new SystemEvents($queue);
        $received = [];
        foreach (['system.minute', 'system.hour', 'system.day'] as $name) {
            $events->subscribe('example', $name, static function (string $event, array $data) use (&$received): void {
                $received[] = [$event, $data];
            });
        }
        $ticker = new SystemEventTicker($events);
        $ticker->tick(CarbonImmutable::parse('2026-10-27 23:59:45', 'Europe/Moscow'));
        self::assertCount(0, $jobs);
        $ticker->tick(CarbonImmutable::parse('2026-10-28 00:00:01', 'Europe/Moscow'));
        $ticker->tick(CarbonImmutable::parse('2026-10-28 00:00:30', 'Europe/Moscow'));
        self::assertSame(['system.minute', 'system.hour', 'system.day'], array_column(array_column($jobs, 2), 'event'));
        foreach ($jobs as [$queueName, $job, $payload]) {
            self::assertSame('module.example', $queueName);
            self::assertSame('module.system.event', $job);
            $events->deliver($payload);
        }
        self::assertCount(3, $received);
        self::assertSame('2026-10-28T00:00:00+03:00', $received[0][1]['occurred_at']);
        $jobs = [];
        $ticker->tick(CarbonImmutable::parse('2026-10-28 04:32:00', 'Europe/Moscow'));
        self::assertSame(['system.minute'], array_column(array_column($jobs, 2), 'event'));
    }
}
