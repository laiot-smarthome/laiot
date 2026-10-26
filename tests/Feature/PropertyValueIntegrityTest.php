<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Events\PropertyEventRepository;
use MajorDoMo\History\HistoryRepository;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Methods\MethodRunner;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Properties\ValueRepository;
use MajorDoMo\Support\Clock;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Целостность записи значений независимо от HTTP и модульного SDK.
 *
 * 2026-10-26 — чужой класс не изменяется, время создания значения сохраняется.
 */
final class PropertyValueIntegrityTest extends TestCase
{
    private DatabaseManager $database;
    private PropertyValueService $service;
    private ValueRepository $values;

    protected function setUp(): void
    {
        $this->database = new DatabaseManager([
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        foreach ([
            '2026_10_26_000014_create_properties.php',
            '2026_10_26_000016_create_objects_and_values.php',
            '2026_10_26_000017_create_property_history.php',
            '2026_10_26_000019_create_property_events.php',
        ] as $name) {
            $migration = require dirname(__DIR__, 2) . '/database/migrations/' . $name;
            $migration($this->database->connection());
        }
        $objects = new ObjectRepository($this->database);
        $properties = new PropertyRepository($this->database);
        $history = new HistoryRepository($this->database);
        $methods = new MethodRepository($this->database);
        $this->values = new ValueRepository($this->database);
        $this->service = new PropertyValueService(
            $this->database,
            $objects,
            $properties,
            $this->values,
            $history,
            $methods,
            new MethodRunner($objects, $methods),
            new PropertyEventRepository($this->database, new Clock()),
            new NullLogger()
        );
        $objects->create('Датчик', 1, 0, '');
        $properties->create(1, 'temperature', '', 'string', true, '');
        $properties->create(2, 'temperature', '', 'string', true, '');
        $properties->create(0, 'global', '', 'string', false, '');
    }

    public function testWritingByIdRejectsPropertyOfAnotherClassWithoutSideEffects(): void
    {
        self::assertFalse($this->service->setValue(1, 2, 'foreign'));
        foreach (['property_values', 'property_history', 'property_events'] as $table) {
            self::assertSame(0, $this->database->connection()->table($table)->count());
        }
        self::assertTrue($this->service->setValue(1, 1, 'local'));
        self::assertSame('local', $this->service->get('Датчик', 'temperature'));
        self::assertTrue($this->service->setValue(1, 3, 'global'));
        self::assertSame('global', $this->service->get('Датчик', 'global'));
    }

    public function testUpdatingOrRestoringValuePreservesItsCreationTime(): void
    {
        $createdAt = '2020-01-02 03:04:05';
        $connection = $this->database->connection();
        $connection->table('property_values')->insert([
            'object_id' => 1, 'property_id' => 1, 'value' => 'old',
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
        $this->values->write(1, 1, 'updated');
        $row = $connection->table('property_values')->first();
        self::assertNotNull($row);
        self::assertSame($createdAt, $row->created_at);
        self::assertSame('updated', $row->value);
        self::assertNotSame($createdAt, $row->updated_at);

        $this->values->delete((int)$row->id);
        $this->values->write(1, 1, 'restored');
        $restored = $connection->table('property_values')->first();
        self::assertNotNull($restored);
        self::assertSame($row->id, $restored->id);
        self::assertSame($createdAt, $restored->created_at);
        self::assertNull($restored->deleted_at);
        self::assertSame('restored', $restored->value);
        self::assertSame(1, $connection->table('property_values')->count());
    }
}
