<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Events\PropertyExecutionRepository;
use LaIOT\Services\Queue\DatabaseQueue;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Две очереди моделируют разные процессы на общей изолированной SQLite.
 *
 * 2026-10-27 — проверены зависимости, порядок свойства и восстановление.
 */
final class PropertyExecutionTest extends TestCase
{
    private DatabaseManager $database;
    private PropertyExecutionRepository $executions;
    private DatabaseQueue $queue;
    private int $now = 1700000000;

    protected function setUp(): void
    {
        $this->database = new DatabaseManager(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->executions = new PropertyExecutionRepository($this->database);
        $this->executions->ensureTable();
        $this->queue = new DatabaseQueue($this->database, ['max_attempts' => 2], fn (): int => $this->now);
    }

    public function testChildWaitsForParentWithoutBlockingIndependentPropertyInSameLane(): void
    {
        $parent = $this->executions->create(0, 'property:1:1');
        $child = $this->executions->create($parent, 'property:1:2');
        $other = $this->executions->create(0, 'property:1:3');
        $this->queue->push('events.0', 'property.changed', ['execution_id' => $parent]);
        $this->queue->push('events.1', 'property.changed', ['execution_id' => $child]);
        $this->queue->push('events.1', 'property.changed', ['execution_id' => $other]);
        $running = $this->queue->pop('events.0');
        self::assertNotNull($running);
        $this->executions->update($parent, ['status' => 'running']);

        $independent = $this->queue->pop('events.1');
        self::assertNotNull($independent);
        self::assertSame($other, $independent->payload['execution_id']);
        self::assertNull($this->queue->pop('events.1'));

        $this->executions->update($parent, ['status' => 'ok']);
        $this->queue->ack($running);
        $secondProcess = new DatabaseQueue($this->database, [], fn (): int => $this->now);
        self::assertSame($child, $secondProcess->pop('events.1')?->payload['execution_id']);
    }

    public function testRetryBlocksSamePropertyButNotOtherProperties(): void
    {
        $first = $this->executions->create(0, 'property:1:1');
        $next = $this->executions->create(0, 'property:1:1');
        $other = $this->executions->create(0, 'property:1:2');
        foreach ([$first, $next, $other] as $id) {
            $this->queue->push('events.0', 'property.changed', ['execution_id' => $id]);
        }
        $job = $this->queue->pop('events.0');
        self::assertNotNull($job);
        $this->queue->fail($job, new RuntimeException('transient database error'));
        self::assertSame($other, $this->queue->pop('events.0')?->payload['execution_id']);
        self::assertNull($this->queue->pop('events.0'));

        $this->now += 60;
        $recovery = new DatabaseQueue($this->database, [], fn (): int => $this->now);
        $retry = $recovery->pop('events.0');
        self::assertNotNull($retry);
        self::assertSame($first, $retry->payload['execution_id']);
        $this->executions->update($first, ['status' => 'ok']);
        $recovery->ack($retry);
        self::assertSame($next, $recovery->pop('events.0')?->payload['execution_id']);
    }

    public function testCrashLeavesChildrenWaitingUntilReservationIsReclaimed(): void
    {
        $parent = $this->executions->create(0, 'property:1:1');
        $child = $this->executions->create($parent, 'property:1:2');
        $this->queue->push('events.0', 'property.changed', ['execution_id' => $parent]);
        $this->queue->push('events.1', 'property.changed', ['execution_id' => $child]);
        $original = $this->queue->pop('events.0');
        self::assertNotNull($original);
        self::assertNull($this->queue->pop('events.1'));
        $this->now += 300;
        $recovery = new DatabaseQueue($this->database, [], fn (): int => $this->now);
        $retry = $recovery->pop('events.0');
        self::assertNotNull($retry);
        self::assertSame($original->id, $retry->id);
        $this->queue->ack($original);
        self::assertSame(1, $recovery->stats('events.0')['reserved']);
        $this->executions->update($parent, ['status' => 'ok']);
        $recovery->ack($retry);
        self::assertSame($child, $recovery->pop('events.1')?->payload['execution_id']);
    }

    public function testMigrationPreservesAndAdoptsExistingQueuedCommands(): void
    {
        $connection = $this->database->connection();
        foreach (['2026_10_26_000001_create_queue_jobs.php', '2026_10_27_000003_add_queue_reservation_token.php'] as $name) {
            $migration = require dirname(__DIR__, 2) . '/database/migrations/' . $name;
            $migration($connection);
        }
        $now = date('Y-m-d H:i:s');
        $connection->table('queue_jobs')->insert([
            'queue' => 'events.0', 'job' => 'property.changed',
            'payload' => json_encode(['object_id' => 1, 'property_id' => 2, 'new_value' => 'on'], JSON_THROW_ON_ERROR),
            'available_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_10_27_000011_create_property_executions.php';
        $migration($connection);
        $migration($connection);
        self::assertSame(1, $connection->table('queue_jobs')->count());
        self::assertSame(1, $connection->table('property_executions')->count());
        $queue = new DatabaseQueue($this->database);
        $job = $queue->pop('events.0');
        self::assertNotNull($job);
        self::assertSame('on', $job->payload['new_value']);
        $step = $this->executions->find((int)$job->payload['execution_id']);
        self::assertSame('property:1:2', $step['stream']);
    }

    public function testRepeatedCrashesFailChainAndReleaseIndependentCommands(): void
    {
        $first = $this->executions->create(0, 'property:1:1');
        $next = $this->executions->create(0, 'property:1:1');
        $this->queue->push('events.0', 'property.changed', ['execution_id' => $first]);
        $this->queue->push('events.0', 'property.changed', ['execution_id' => $next]);
        self::assertNotNull($this->queue->pop('events.0'));
        $this->now += 300;
        $recovery = new DatabaseQueue($this->database, ['max_attempts' => 2], fn (): int => $this->now);
        self::assertNotNull($recovery->pop('events.0'));
        $this->now += 300;
        $thirdProcess = new DatabaseQueue($this->database, ['max_attempts' => 2], fn (): int => $this->now);
        self::assertSame($next, $thirdProcess->pop('events.0')?->payload['execution_id']);
        self::assertTrue($this->executions->cancelled($first));
        self::assertSame(1, $thirdProcess->stats('events.0')['failed']);
    }

    public function testManualRetryStartsNewChainWithoutRepeatingRecordedHistory(): void
    {
        $execution = $this->executions->create(0, 'property:1:1');
        $this->executions->update($execution, ['history_done' => true]);
        $queue = new DatabaseQueue($this->database, ['max_attempts' => 1]);
        $id = $queue->push('events.0', 'property.changed', ['execution_id' => $execution]);
        $job = $queue->pop('events.0');
        self::assertNotNull($job);
        $queue->fail($job, new RuntimeException('failed'));
        self::assertTrue($queue->retry($id));
        $retried = $queue->pop('events.0');
        self::assertNotNull($retried);
        $newId = (int)$retried->payload['execution_id'];
        self::assertNotSame($execution, $newId);
        self::assertTrue($this->executions->cancelled($execution));
        self::assertFalse($this->executions->cancelled($newId));
        self::assertTrue($this->executions->find($newId)['history_done']);
    }
}
