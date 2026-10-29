<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Cache\CacheFactory;
use LaIOT\Services\Worker\BackgroundTaskState;
use PHPUnit\Framework\TestCase;

/** 2026-10-29 — heartbeat сохраняет состояние непрерывной core-службы более суток. */
final class CacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/laiot-cache-' . bin2hex(random_bytes(4));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function testArrayStoreAndRemember(): void
    {
        $cache = CacheFactory::create(['driver' => 'array'], new DatabaseManager([]));
        $calls = 0;

        $cache->put('answer', 42);
        self::assertSame(42, $cache->get('answer'));
        self::assertTrue($cache->has('answer'));
        self::assertSame(42, $cache->remember('answer', 60, function () use (&$calls): int {
            $calls++;

            return 99;
        }));
        self::assertSame(0, $calls);

        self::assertSame(99, $cache->remember('missing', 60, function () use (&$calls): int {
            $calls++;

            return 99;
        }));
        self::assertSame(1, $calls);
        self::assertTrue($cache->forget('answer'));
        self::assertNull($cache->get('answer'));
    }

    public function testCoreHeartbeatKeepsTaskVisibleBeyondOneDay(): void
    {
        $cache = CacheFactory::create(['driver' => 'array'], new DatabaseManager([]));
        $state = new BackgroundTaskState($cache);
        $start = new \DateTimeImmutable('2030-01-01 00:00:00 UTC');
        Carbon::setTestNow($start);
        try {
            $cache->put('clock-probe', true, 1);
            self::assertTrue($state->acquireTask('core', 'websocket', 123));
            $state->start('core', 'websocket', 123, '');
            $started = $state->get('core', 'websocket')['started_at'];
            for ($minute = 1; $minute <= 1500; $minute++) {
                Carbon::setTestNow($start->modify('+' . $minute . ' minutes'));
                $state->refreshTask('core', 'websocket', 123);
            }
            self::assertNull($cache->get('clock-probe'));
            $last = $state->get('core', 'websocket');
            self::assertNotNull($last);
            self::assertSame('running', $last['status']);
            self::assertSame(123, $last['pid']);
            self::assertSame($started, $last['started_at']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function testFileStorePersistsBetweenInstances(): void
    {
        $config = ['driver' => 'file', 'path' => $this->directory];
        $first = CacheFactory::create($config, new DatabaseManager([]));
        $first->put('persisted', ['ok' => true]);

        $second = CacheFactory::create($config, new DatabaseManager([]));
        self::assertSame(['ok' => true], $second->get('persisted'));
    }

    public function testDatabaseStoreUsesCacheTable(): void
    {
        $database = new DatabaseManager([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $database->schema()->create('cache', static function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value');
            $table->integer('expiration');
        });

        $cache = CacheFactory::create(['driver' => 'database', 'table' => 'cache'], $database);
        $cache->put('db-key', 'db-value');

        self::assertSame('db-value', $cache->get('db-key'));
        self::assertTrue($cache->forget('db-key'));
        self::assertNull($cache->get('db-key'));
    }

    public function testRedisStoreCanBeConstructedLazily(): void
    {
        $cache = CacheFactory::create([
            'driver' => 'redis',
            'redis' => ['host' => '127.0.0.1', 'port' => 6379, 'database' => 0],
        ], new DatabaseManager([]));

        self::assertInstanceOf(\LaIOT\Services\Cache\CacheStore::class, $cache);
    }

    public function testInvalidKeyIsRejected(): void
    {
        $cache = CacheFactory::create(['driver' => 'array'], new DatabaseManager([]));

        $this->expectException(InvalidArgumentException::class);
        $cache->put('bad key', 'value');
    }
}
