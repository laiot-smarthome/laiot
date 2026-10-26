<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Queue\DatabaseQueue;
use MajorDoMo\Queue\Job;
use MajorDoMo\Queue\JobRegistry;
use MajorDoMo\Queue\QueueWorker;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class TypedJobTest extends TestCase
{
    public function testTypedJobKeepsNameAndPayload(): void
    {
        $queue = new DatabaseQueue(new DatabaseManager([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]));

        $queue->pushJob(new DemoEmailJob('user@example.com', 'hello'));
        $job = $queue->pop('default');

        self::assertNotNull($job);
        self::assertSame('demo.email', $job->job);
        self::assertSame(['to' => 'user@example.com', 'text' => 'hello'], $job->payload);
    }

    public function testTypedJobRunsRegisteredHandler(): void
    {
        $queue = new DatabaseQueue(new DatabaseManager([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]));
        $registry = new JobRegistry();
        $sent = [];
        $registry->register('demo.email', static function (array $payload) use (&$sent): void {
            $sent[] = $payload;
        });

        $queue->pushJob(new DemoEmailJob('a@b.c', 'typed'));
        $worker = new QueueWorker($queue, $registry, new NullLogger());

        self::assertTrue($worker->work('default'));
        self::assertSame([['to' => 'a@b.c', 'text' => 'typed']], $sent);
    }
}

final class DemoEmailJob implements Job
{
    public function __construct(private readonly string $to, private readonly string $text)
    {
    }

    public function name(): string
    {
        return 'demo.email';
    }

    public function payload(): array
    {
        return ['to' => $this->to, 'text' => $this->text];
    }
}
