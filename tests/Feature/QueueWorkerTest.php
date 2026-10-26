<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Queue\DatabaseQueue;
use MajorDoMo\Queue\JobRegistry;
use MajorDoMo\Queue\QueueWorker;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class QueueWorkerTest extends TestCase
{
    public function testWorkerRunsHandlerAndAcknowledgesJob(): void
    {
        $queue = $this->queue();
        $registry = new JobRegistry();
        $called = [];
        $registry->register('demo.job', static function (array $payload) use (&$called): void {
            $called[] = $payload;
        });

        $queue->push('default', 'demo.job', ['value' => 42]);
        $worker = new QueueWorker($queue, $registry, new NullLogger());

        self::assertTrue($worker->work('default'));
        self::assertSame([['value' => 42]], $called);
        self::assertNull($queue->pop('default'));
    }

    public function testWorkerMarksJobFailedWhenHandlerThrows(): void
    {
        $queue = $this->queue(maxAttempts: 1);
        $registry = new JobRegistry();
        $registry->register('demo.fail', static function (): void {
            throw new RuntimeException('boom');
        });

        $queue->push('default', 'demo.fail');
        $worker = new QueueWorker($queue, $registry, new NullLogger());

        self::assertTrue($worker->work('default'));
        self::assertSame(['pending' => 0, 'reserved' => 0, 'failed' => 1], $queue->stats('default'));
    }

    private function queue(int $maxAttempts = 3): DatabaseQueue
    {
        return new DatabaseQueue(new DatabaseManager([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]), [
            'max_attempts' => $maxAttempts,
            'retry_delay' => 1,
        ]);
    }
}
