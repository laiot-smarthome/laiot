<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Queue\DatabaseQueue;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
            ->whereNotNull('FAILED_AT')
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

    public function testInvalidQueueNameIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->queue->push('bad queue', 'demo.job');
    }
}
