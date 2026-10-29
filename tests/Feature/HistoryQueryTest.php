<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\Database\DatabaseManager;
use LaIOT\Services\History\HistoryRepository;
use PHPUnit\Framework\TestCase;

/**
 * Выборка истории: период, порядок, лимит и агрегаты одним SQL.
 *
 * 2026-10-27 — тест создан вместе с HistoryQuery.
 */
final class HistoryQueryTest extends TestCase
{
    private DatabaseManager $database;
    private HistoryRepository $history;

    protected function setUp(): void
    {
        $this->database = new DatabaseManager([
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_10_26_000017_create_property_history.php';
        $migration($this->database->connection());
        $this->history = new HistoryRepository($this->database);

        $now = date('Y-m-d H:i:s');
        foreach ([
            ['', '2026-10-01 09:00:00'],
            ['10', '2026-10-01 10:00:00'],
            ['20', '2026-10-01 11:00:00'],
            ['30', '2026-10-01 12:00:00'],
        ] as [$value, $created]) {
            $this->database->connection()->table('property_history')->insert([
                'object_id' => 1,
                'property_id' => 2,
                'value' => $value,
                'created_at' => $created,
                'updated_at' => $created,
                'deleted_at' => null,
            ]);
        }
    }

    public function testAllValuesAndLastValue(): void
    {
        $query = $this->history->query(1, 2);

        self::assertSame(4, $query->count());
        self::assertSame('30', $query->lastValue());
        self::assertSame(['30', '20', '10', ''], array_column($query->values(), 'value'));
        self::assertSame(['', '10', '20', '30'], array_column($query->oldestFirst()->values(), 'value'));
    }

    public function testPeriodIsInclusiveAndLimitsResult(): void
    {
        $query = $this->history->query(1, 2)
            ->from('2026-10-01 10:00:00')
            ->to('2026-10-01 11:00:00');

        self::assertSame(2, $query->count());
        self::assertSame(['20', '10'], array_column($query->values(), 'value'));

        $limited = $this->history->query(1, 2)->last(2);
        self::assertCount(2, $limited->values());
        self::assertSame(4, $limited->count());
    }

    public function testNumericAggregatesSkipEmptyValues(): void
    {
        $stats = $this->history->query(1, 2)->stats();

        self::assertSame(4, $stats['count']);
        self::assertSame(10.0, $stats['min']);
        self::assertSame(30.0, $stats['max']);
        self::assertSame(20.0, $stats['avg']);
        self::assertSame(60.0, $stats['sum']);
        self::assertSame('30', $stats['last']);
    }

    public function testAggregatesRespectPeriod(): void
    {
        $query = $this->history->query(1, 2)
            ->from('2026-10-01 11:00:00')
            ->to('2026-10-01 12:00:00');

        self::assertSame(25.0, $query->avg());
        self::assertSame(50.0, $query->sum());
        self::assertSame(2, $query->count());
    }

    public function testEmptySelectionHasNullAggregates(): void
    {
        $query = $this->history->query(1, 2)->from('2030-01-01 00:00:00');

        self::assertSame(0, $query->count());
        self::assertNull($query->avg());
        self::assertNull($query->sum());
        self::assertNull($query->lastValue());
    }
}
