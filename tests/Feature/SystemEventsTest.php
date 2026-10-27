<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use MajorDoMo\Events\SystemEvents;
use MajorDoMo\Events\SystemEventTicker;
use MajorDoMo\Queue\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 2026-10-27 — события модулей имеют вид module.<id>.*, ядра — system.*.
 * 2026-10-27 — проверка календарных границ и доставки системных событий.
 */
final class SystemEventsTest extends TestCase
{
    public function testEventNamesFollowOwnerPrefix(): void
    {
        $queue = $this->createMock(Queue::class);
        $pushed = [];
        $queue->method('push')->willReturnCallback(
            static function (string $queue, string $job, array $payload) use (&$pushed): int {
                $pushed[] = [$queue, $job, $payload];

                return count($pushed);
            }
        );
        $events = new SystemEvents($queue);
        $events->subscribe('demo', 'system.minute', static function (): void {
        });
        $events->subscribe('demo', 'module.other.ready', static function (): void {
        });

        $events->emit('system.minute');
        $events->emit('module.other.ready');

        self::assertSame(
            ['system.minute', 'module.other.ready'],
            array_column(array_column($pushed, 2), 'event')
        );
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function invalidEventNames(): array
    {
        return [
            'пустое' => [''],
            'без владельца' => ['demo.ready'],
            'без события' => ['module.demo'],
            'плохое имя модуля' => ['module.9bad.ready'],
            'пустое имя ядра' => ['system.'],
            'пустое имя модуля' => ['module..ready'],
        ];
    }

    #[DataProvider('invalidEventNames')]
    public function testInvalidEventNameIsRejected(string $event): void
    {
        $events = new SystemEvents($this->createMock(Queue::class));

        $this->expectException(InvalidArgumentException::class);
        $events->emit($event);
    }

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
