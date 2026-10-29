<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Events\PropertyChangedJob;
use LaIOT\Services\Events\PropertyEventQueue;
use LaIOT\Services\Queue\DatabaseQueue;
use PHPUnit\Framework\TestCase;

/**
 * Полосы очереди событий: одно свойство — одна полоса, порядок сохраняется.
 *
 * 2026-10-27 — тест создан вместе с полосами событий.
 */
final class PropertyEventQueueTest extends TestCase
{
    private DatabaseQueue $queue;
    private PropertyEventQueue $events;

    public function testSingleLaneKeepsQueueName(): void
    {
        $this->make(1);

        self::assertSame(1, $this->events->lanes());
        self::assertSame(['events'], $this->events->queueNames());
        self::assertSame(0, $this->events->laneFor(10, 20));
    }

    public function testSamePropertyAlwaysUsesSameLane(): void
    {
        $this->make(4);

        self::assertSame(4, $this->events->lanes());
        self::assertSame(['events.0', 'events.1', 'events.2', 'events.3'], $this->events->queueNames());
        self::assertSame($this->events->laneFor(1, 1), $this->events->laneFor(1, 1));
        self::assertSame(
            $this->events->queueName($this->events->laneFor(1, 1)),
            $this->events->queueName($this->events->laneFor(1, 1))
        );
    }

    public function testPushGoesToPropertyLaneAndPendingSumsLanes(): void
    {
        $this->make(4);
        $job = new PropertyChangedJob(['object_id' => 1, 'property_id' => 2]);

        $id = $this->events->push($job, 1, 2);
        $lane = $this->events->laneFor(1, 2);

        self::assertGreaterThan(0, $id);
        self::assertSame(1, $this->events->pending());
        self::assertSame(1, $this->queue->stats($this->events->queueName($lane))['pending']);
        self::assertSame(0, $this->queue->stats($this->events->queueName(($lane + 1) % 4))['pending']);
    }

    private function make(int $lanes): void
    {
        $database = new DatabaseManager([
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        $this->queue = new DatabaseQueue($database);
        $this->events = new PropertyEventQueue($this->queue, ['events_lanes' => $lanes]);
    }
}
