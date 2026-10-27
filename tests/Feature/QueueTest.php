<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Queue\DatabaseQueue;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * 2026-10-26 — проверка возврата задания после просроченной резервации.
 * 2026-10-27 — проверка защиты от старого владельца резервации.
 */
final class QueueTest extends TestCase
{
    private DatabaseManager $database;
    private int $now = 1_700_000_000;
    private DatabaseQueue $queue;

    protected function setUp(): void
    {
        $this->database = new DatabaseManager([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->queue = new DatabaseQueue($this->database, [
            'table' => 'queue_jobs',
            'max_attempts' => 2,
            'retry_delay' => 60,
            'reservation_timeout' => 300,
        ], fn (): int => $this->now);
    }

    public function testPushPopAck(): void
    {
        $id = $this->queue->push('default', 'demo.job', ['value' => 42]);
        $job = $this->queue->pop('default');

        self::assertNotNull($job);
        self::assertSame($id, $job->id);
        self::assertSame('demo.job', $job->job);
        self::assertSame(['value' => 42], $job->payload);
        self::assertSame(0, $job->attempts);

        $this->queue->ack($job);
        self::assertNull($this->queue->pop('default'));
    }

    public function testDelayedJobIsNotAvailableEarly(): void
    {
        $this->queue->push('default', 'demo.job', [], 30);
        self::assertNull($this->queue->pop('default'));

        $this->now += 31;
        self::assertNotNull($this->queue->pop('default'));
    }

    public function testFailedJobIsRetriedThenMarkedFailed(): void
    {
        $this->queue->push('default', 'demo.job');
        $job = $this->queue->pop('default');
        self::assertNotNull($job);

        $this->queue->fail($job, new RuntimeException('first failure'));
        self::assertNull($this->queue->pop('default'));

        $this->now += 61;
        $job = $this->queue->pop('default');
        self::assertInstanceOf(\MajorDoMo\Queue\QueueJob::class, $job);
        self::assertSame(1, $job->attempts);

        $this->queue->fail($job, new RuntimeException('second failure'));
        self::assertNull($this->queue->pop('default'));

        self::assertSame(1, $this->database->connection()
            ->table('queue_jobs')
            ->whereNotNull('failed_at')
            ->count());
    }

    public function testStatsShowPendingAndReserved(): void
    {
        $this->queue->push('default', 'demo.job');
        self::assertSame(['pending' => 1, 'reserved' => 0, 'failed' => 0], $this->queue->stats('default'));

        $job = $this->queue->pop('default');
        self::assertNotNull($job);
        self::assertSame(['pending' => 0, 'reserved' => 1, 'failed' => 0], $this->queue->stats('default'));

        $this->queue->ack($job);
        self::assertSame(['pending' => 0, 'reserved' => 0, 'failed' => 0], $this->queue->stats('default'));
    }

    public function testFailedJobCanBeListedRetriedAndForgotten(): void
    {
        $queue = new DatabaseQueue($this->database, [
            'max_attempts' => 1,
            'retry_delay' => 1,
        ], fn (): int => $this->now);
        $queue->push('default', 'demo.job');
        $job = $queue->pop('default');
        self::assertNotNull($job);
        $queue->fail($job, new RuntimeException('boom'));

        $failed = $queue->failed('default');
        self::assertCount(1, $failed);
        self::assertSame('demo.job', $failed[0]['job']);
        self::assertSame('boom', $failed[0]['error']);

        self::assertTrue($queue->retry($failed[0]['id']));
        self::assertSame(1, $queue->stats('default')['pending']);

        $job = $queue->pop('default');
        self::assertNotNull($job);
        $queue->fail($job, new RuntimeException('again'));
        self::assertTrue($queue->forget($failed[0]['id']));
        self::assertSame([], $queue->failed('default'));
    }

    public function testInvalidQueueNameIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->queue->push('bad queue', 'demo.job');
    }

    public function testExpiredReservationDoesNotBlockFollowingJobs(): void
    {
        $firstId = $this->queue->push('default', 'demo.first');
        self::assertNotNull($this->queue->pop('default'));
        self::assertNull($this->queue->pop('default'));
        $secondId = $this->queue->push('default', 'demo.second');

        $this->now += 300;
        $recovery = new DatabaseQueue($this->database, [
            'reservation_timeout' => 300,
        ], fn (): int => $this->now);
        $reclaimed = $recovery->pop('default');
        self::assertNotNull($reclaimed);
        self::assertSame($firstId, $reclaimed->id);
        $second = $recovery->pop('default');
        self::assertNotNull($second);
        self::assertSame($secondId, $second->id);
        self::assertNull($recovery->pop('default'));
    }

    public function testExpiredOwnerCannotAcknowledgeOrFailNewReservation(): void
    {
        $this->queue->push('default', 'demo.job');
        $old = $this->queue->pop('default');
        self::assertNotNull($old);
        $this->now += 300;
        $current = $this->queue->pop('default');
        self::assertNotNull($current);

        $this->queue->ack($old);
        $this->queue->fail($old, new RuntimeException('late failure'));
        self::assertSame(['pending' => 0, 'reserved' => 1, 'failed' => 0], $this->queue->stats('default'));
        $this->queue->ack($current);
        self::assertSame(['pending' => 0, 'reserved' => 0, 'failed' => 0], $this->queue->stats('default'));
    }
}
