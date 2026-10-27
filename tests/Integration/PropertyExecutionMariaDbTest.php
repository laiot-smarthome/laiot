<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Integration;

use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Events\PropertyExecutionRepository;
use MajorDoMo\Queue\DatabaseQueue;
use PHPUnit\Framework\TestCase;

/**
 * Два соединения с одноразовым MariaDB: выбор шага и сохранение квитанций.
 *
 * 2026-10-27 — проверка реального SQL с уникальным префиксом тестовых таблиц.
 */
final class PropertyExecutionMariaDbTest extends TestCase
{
    public function testDependenciesReservationsAndReceiptsAcrossConnections(): void
    {
        $host = getenv('MYDOMO_TEST_DB_HOST');
        if ($host === false || $host === '') {
            self::markTestSkipped('Одноразовый MariaDB-стенд не настроен.');
        }
        $config = [
            'driver' => 'mysql', 'host' => $host,
            'port' => (int)(getenv('MYDOMO_TEST_DB_PORT') ?: 3306),
            'database' => (string)(getenv('MYDOMO_TEST_DB_NAME') ?: 'mydomo_execution_test'),
            'username' => (string)(getenv('MYDOMO_TEST_DB_USER') ?: 'root'),
            'password' => (string)(getenv('MYDOMO_TEST_DB_PASSWORD') ?: ''),
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
            'prefix' => 'execution_test_' . bin2hex(random_bytes(4)) . '_',
        ];
        $first = new DatabaseManager($config);
        $second = new DatabaseManager($config);
        $states = new PropertyExecutionRepository($first);
        $states->ensureTable();
        $queue = new DatabaseQueue($first);
        $otherQueue = new DatabaseQueue($second);
        $otherStates = new PropertyExecutionRepository($second);
        try {
            $parent = $first->transaction(fn (): int => $states->create(0, 'property:1:1'));
            $child = $first->transaction(fn (): int => $states->create($parent, 'property:1:2'));
            $independent = $first->transaction(fn (): int => $states->create(0, 'property:1:3'));
            $queue->push('events.0', 'property.changed', ['execution_id' => $parent]);
            $queue->push('events.1', 'property.changed', ['execution_id' => $child]);
            $queue->push('events.1', 'property.changed', ['execution_id' => $independent]);

            $running = $queue->pop('events.0');
            self::assertNotNull($running);
            self::assertNull($otherQueue->pop('events.0'));
            $other = $otherQueue->pop('events.1');
            self::assertNotNull($other);
            self::assertSame($independent, $other->payload['execution_id']);
            $otherStates->update($independent, ['status' => 'ok']);
            $otherQueue->ack($other);
            $states->once($parent, 'history_done', fn () => $states->update($parent, ['message' => 'receipt']));
            $otherStates->once($parent, 'history_done', static function (): void {
                self::fail('Подтверждённый этап не должен повторяться другим соединением.');
            });
            $states->update($parent, ['status' => 'ok']);
            $queue->ack($running);
            $readyChild = $otherQueue->pop('events.1');
            self::assertNotNull($readyChild);
            self::assertSame($child, $readyChild->payload['execution_id']);
            $otherStates->update($child, ['status' => 'ok']);
            $otherQueue->ack($readyChild);

            $started = microtime(true);
            for ($i = 0; $i < 100; $i++) {
                $id = $first->transaction(fn (): int => $states->create(0, 'property:2:1'));
                $queue->push('events.0', 'property.changed', ['execution_id' => $id]);
                $job = $otherQueue->pop('events.0');
                self::assertNotNull($job);
                self::assertSame($id, $job->payload['execution_id']);
                $otherStates->update($id, ['status' => 'ok']);
                $otherQueue->ack($job);
            }
            fwrite(STDERR, sprintf("\nMariaDB: 100 шагов состояния/очереди — %.1f ms, без пользовательского кода.\n", (microtime(true) - $started) * 1000));
            self::assertSame(0, $queue->stats('events.0')['pending']);
        } finally {
            $first->schema()->dropIfExists('queue_jobs');
            $first->schema()->dropIfExists('property_executions');
        }
    }
}
