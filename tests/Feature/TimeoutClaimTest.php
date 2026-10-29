<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Runtime\TimeoutRepository;
use PHPUnit\Framework\TestCase;

/**
 * Аренда таймера: два процесса не выполняют одну запись, ошибка освобождает.
 *
 * 2026-10-27 — тест создан вместе с claim для runtime_timeouts.
 * 2026-10-27 — ошибки не вытесняют следующие таймеры из выборки.
 */
final class TimeoutClaimTest extends TestCase
{
    private DatabaseManager $database;
    private TimeoutRepository $timeouts;

    protected function setUp(): void
    {
        $this->database = new DatabaseManager([
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        foreach ([
            '2026_10_26_000018_create_runtime_timeouts.php',
            '2026_10_27_000004_add_runtime_timeouts_claim.php',
        ] as $name) {
            $migration = require dirname(__DIR__, 2) . '/database/migrations/' . $name;
            $migration($this->database->connection());
        }
        $this->timeouts = new TimeoutRepository($this->database);
    }

    public function testClaimedTimerIsNotGivenToSecondProcess(): void
    {
        $this->timeouts->set('once', 'return 1;', 0);

        $first = $this->claimOne();
        self::assertSame(1, $first['id']);
        self::assertNotSame('', $first['token']);

        self::assertSame([], $this->timeouts->claim());
        self::assertCount(1, $this->timeouts->due());
    }

    public function testWrongTokenCannotFinishOrReleaseTimer(): void
    {
        $id = $this->timeouts->set('once', 'return 1;', 0);
        $claimed = $this->claimOne();

        $this->timeouts->markExecuted($id, 'чужой-токен');
        $this->timeouts->release($id, 'чужой-токен');

        self::assertNull($this->database->connection()->table('runtime_timeouts')->value('executed_at'));
        self::assertSame([], $this->timeouts->claim());
    }

    public function testReleaseDelaysRetryAndDoesNotBlockFollowingTimer(): void
    {
        $id = $this->timeouts->set('once', 'return 1;', 0);
        $claimed = $this->claimOne();

        $this->timeouts->release($id, $claimed['token']);
        self::assertSame([], $this->timeouts->claim());
        $nextId = $this->timeouts->set('next', 'return 2;', 0);
        self::assertSame($nextId, $this->claimOne()['id']);
        $this->database->connection()->table('runtime_timeouts')
            ->where('id', $id)
            ->update(['claimed_at' => date('Y-m-d H:i:s', time() - 301)]);
        $retried = $this->claimOne();
        self::assertSame($id, $retried['id']);
        self::assertNotSame($claimed['token'], $retried['token']);
    }

    public function testExecutedTimerIsGoneAndExpiredClaimIsReclaimable(): void
    {
        $id = $this->timeouts->set('once', 'return 1;', 0);
        $claimed = $this->claimOne();
        $this->timeouts->markExecuted($id, $claimed['token']);

        self::assertSame([], $this->timeouts->due());
        self::assertSame([], $this->timeouts->claim());

        $second = $this->timeouts->set('second', 'return 2;', 0);
        $this->timeouts->claim();
        $this->database->connection()->table('runtime_timeouts')
            ->where('id', $second)
            ->update(['claimed_at' => date('Y-m-d H:i:s', time() - 3600)]);

        $reclaimed = $this->claimOne();
        self::assertSame($second, $reclaimed['id']);
    }

    /**
     * @return array{id:int,title:string,code:string,token:string}
     */
    private function claimOne(): array
    {
        $claimed = $this->timeouts->claim();
        $first = array_shift($claimed);
        if ($first === null) {
            self::fail('Ожидался один захваченный таймер.');
        }

        return $first;
    }
}
