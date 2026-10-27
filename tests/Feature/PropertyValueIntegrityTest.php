<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Events\PropertyEventContext;
use MajorDoMo\Events\PropertyEventQueue;
use MajorDoMo\Events\PropertyExecutionRepository;
use MajorDoMo\History\HistoryRepository;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Properties\ValueRepository;
use MajorDoMo\Queue\DatabaseQueue;
use PHPUnit\Framework\TestCase;

/**
 * Целостность записи значений независимо от HTTP и модульного SDK.
 *
 * 2026-10-27 — проверяются наследование класса и объектное свойство:
 * значение пишется только в свойство, которое реально действует у объекта.
 */
final class PropertyValueIntegrityTest extends TestCase
{
    private DatabaseManager $database;
    private PropertyValueService $service;
    private ValueRepository $values;
    private PropertyRepository $properties;
    private ClassRepository $classes;

    protected function setUp(): void
    {
        $this->database = new DatabaseManager([
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        foreach ([
            '2026_10_26_000013_create_classes.php',
            '2026_10_26_000014_create_properties.php',
            '2026_10_26_000015_create_methods.php',
            '2026_10_26_000016_create_objects_and_values.php',
            '2026_10_26_000017_create_property_history.php',
            '2026_10_26_000019_create_property_events.php',
            '2026_10_27_000001_add_class_hierarchy_and_object_members.php',
            '2026_10_27_000002_create_property_subscriptions.php',
            '2026_10_27_000009_add_class_description.php',
        ] as $name) {
            $migration = require dirname(__DIR__, 2) . '/database/migrations/' . $name;
            $migration($this->database->connection());
        }
        $this->classes = new ClassRepository($this->database);
        $objects = new ObjectRepository($this->database, $this->classes);
        $this->properties = new PropertyRepository($this->database, $this->classes);
        $history = new HistoryRepository($this->database);
        $this->values = new ValueRepository($this->database);
        $this->service = new PropertyValueService(
            $this->database,
            $objects,
            $this->properties,
            $this->values,
            $history,
            new PropertyEventContext(),
            new PropertyEventQueue(new DatabaseQueue($this->database), ['events_lanes' => 1]),
            new PropertyExecutionRepository($this->database)
        );

        $this->classes->create('Sensor');
        $this->classes->create('Light');
        $objects->create('Sensor', 1, 0, '');
        $this->properties->create(1, 'temperature', '', 'string', true, '');
        $this->properties->create(2, 'temperature', '', 'string', true, '');
    }

    public function testWritingByIdRejectsPropertyOfAnotherClassWithoutSideEffects(): void
    {
        self::assertFalse($this->service->setValue(1, 2, 'foreign'));
        foreach (['property_values', 'property_history', 'property_events'] as $table) {
            self::assertSame(0, $this->database->connection()->table($table)->count());
        }
        self::assertTrue($this->service->setValue(1, 1, 'local'));
        self::assertSame('local', $this->service->get('Sensor', 'temperature'));
    }

    public function testInheritedAndObjectPropertiesAreWritable(): void
    {
        $childId = $this->classes->create('SmartSensor', 1);
        $this->database->connection()->table('objects')->insert([
            'title' => 'LivingRoom',
            'class_id' => $childId,
            'location_id' => 0,
            'description' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $objectId = (int)$this->database->connection()->table('objects')->where('title', 'LivingRoom')->value('id');

        // Наследуемое свойство родительского класса доступно объекту подкласса.
        self::assertTrue($this->service->setValue($objectId, 1, 'inherited'));
        self::assertSame('inherited', $this->service->get('LivingRoom', 'temperature'));

        // Объектное свойство дополняет классовые и принадлежит только объекту.
        $objectPropertyId = $this->properties->create($childId, 'offset', '', 'float', false, '', $objectId);
        self::assertTrue($this->service->setValue($objectId, $objectPropertyId, 'object'));
        self::assertSame('object', $this->service->get('LivingRoom', 'offset'));
        self::assertSame('object', $this->values->get($objectId, $objectPropertyId));

        $resolved = $this->properties->resolveForObject($objectId, 'offset');
        self::assertNotNull($resolved);
        self::assertSame($objectPropertyId, $resolved['id']);
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
