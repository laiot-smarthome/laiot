<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use InvalidArgumentException;
use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Runtime\PhpSyntaxValidator;
use PHPUnit\Framework\TestCase;

/**
 * Иерархия классов и наследование членов.
 *
 * 2026-10-27 — проверяются подклассы без ограничения глубины, наследование
 * свойств/методов, объектные члены и защита от циклов.
 * 2026-10-27 — имена латиницей и уникальны в ветке; переопределение
 * одноимённым свойством/методом больше не создаётся.
 */
final class ClassHierarchyTest extends TestCase
{
    private DatabaseManager $database;
    private ClassRepository $classes;
    private PropertyRepository $properties;
    private MethodRepository $methods;
    private ObjectRepository $objects;

    protected function setUp(): void
    {
        $this->database = new DatabaseManager([
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        foreach ([
            '2026_10_26_000011_create_locations.php',
            '2026_10_26_000013_create_classes.php',
            '2026_10_26_000014_create_properties.php',
            '2026_10_26_000015_create_methods.php',
            '2026_10_26_000016_create_objects_and_values.php',
            '2026_10_27_000001_add_class_hierarchy_and_object_members.php',
            '2026_10_27_000009_add_class_description.php',
        ] as $name) {
            $migration = require dirname(__DIR__, 2) . '/database/migrations/' . $name;
            $migration($this->database->connection());
        }

        $this->classes = new ClassRepository($this->database);
        $this->properties = new PropertyRepository($this->database, $this->classes);
        $this->methods = new MethodRepository($this->database, $this->classes, new PhpSyntaxValidator());
        $this->objects = new ObjectRepository($this->database, $this->classes);
    }

    public function testTreeWithoutDepthLimitAndLineage(): void
    {
        $root = $this->classes->create('Sensor', 0, 'Базовый датчик');
        $child = $this->classes->create('TemperatureSensor', $root);
        $grandchild = $this->classes->create('OutdoorSensor', $child);

        $rows = $this->classes->flattened();
        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['id']] = $row;
        }
        self::assertSame(0, $byId[$root]['depth']);
        self::assertSame(1, $byId[$child]['depth']);
        self::assertSame(2, $byId[$grandchild]['depth']);
        self::assertSame([$root, $child], $byId[$grandchild]['ancestors']);
        self::assertSame([$root, $child, $grandchild], $this->classes->descendantIds($root));
        self::assertSame([$grandchild, $child, $root], array_column($this->classes->lineage($grandchild), 'id'));
        self::assertSame('Базовый датчик', $this->classes->find($root)['description']);
    }

    public function testSubclassInheritsPropertiesAndMethods(): void
    {
        $root = $this->classes->create('Sensor');
        $child = $this->classes->create('TemperatureSensor', $root);
        $grandchild = $this->classes->create('OutdoorSensor', $child);

        $this->properties->create($root, 'temperature', '', 'float', true, '');
        $this->properties->create($root, 'battery', '', 'int', false, '');
        $this->properties->create($child, 'humidity', '', 'float', false, '');
        $this->methods->create($root, 'refresh', '', 'return 1;');
        $this->methods->create($child, 'refreshChild', '', 'return 2;');

        $properties = $this->properties->effectiveForClass($grandchild);
        $titles = array_column($properties, 'title');
        self::assertSame(['battery', 'humidity', 'temperature'], $titles);
        $byTitle = [];
        foreach ($properties as $property) {
            $byTitle[$property['title']] = $property;
        }
        self::assertSame('inherited', $byTitle['temperature']['origin']);
        self::assertSame($root, $byTitle['temperature']['owner_class_id']);

        $counts = $this->properties->effectiveCounts($this->classes->all());
        self::assertSame(['own' => 1, 'inherited' => 2, 'effective' => 3], $counts[$child]);

        $methods = $this->methods->effectiveForClass($grandchild);
        self::assertSame(['refresh', 'refreshChild'], array_column($methods, 'title'));
        self::assertSame('return 2;', $this->methods->findForClass($grandchild, 'refreshChild')['code'] ?? null);
    }

    public function testDuplicateNamesAreRejectedAcrossLineage(): void
    {
        $root = $this->classes->create('Sensor');
        $child = $this->classes->create('TemperatureSensor', $root);
        $this->properties->create($root, 'temperature', '', 'float', false, '');
        $this->methods->create($root, 'refresh', '', 'return 1;');

        $this->expectException(InvalidArgumentException::class);
        $this->properties->create($child, 'temperature', '', 'float', false, '');
    }

    public function testObjectPropertyNameMustNotRepeatClassOrObjectName(): void
    {
        $root = $this->classes->create('Sensor');
        $objectId = $this->objects->create('LivingRoom', $root, 0, '');
        $this->properties->create($root, 'temperature', '', 'float', false, '');

        try {
            $this->properties->create($root, 'temperature', '', 'float', false, '', $objectId);
            self::fail('Объектное свойство не должно повторять свойство класса.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('уже объявлено', $error->getMessage());
        }

        $this->properties->create($root, 'offset', '', 'float', false, '', $objectId);
        try {
            $this->properties->create($root, 'offset', '', 'float', false, '', $objectId);
            self::fail('Объектное свойство не должно повторяться у объекта.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('этого объекта', $error->getMessage());
        }
    }

    public function testObjectPropertiesAndSubclassObjects(): void
    {
        $root = $this->classes->create('Sensor');
        $child = $this->classes->create('TemperatureSensor', $root);
        $rootProperty = $this->properties->create($root, 'temperature', '', 'float', false, '');
        $objectId = $this->objects->create('LivingRoom', $child, 0, '');
        $objectProperty = $this->properties->create($child, 'offset', '', 'float', false, '', $objectId);

        $object = $this->objects->find($objectId);
        self::assertNotNull($object);
        $rootMember = $this->properties->find($rootProperty);
        $objectMember = $this->properties->find($objectProperty);
        self::assertNotNull($rootMember);
        self::assertNotNull($objectMember);
        self::assertTrue($this->properties->isApplicableToObject($rootMember, $object));
        self::assertTrue($this->properties->isApplicableToObject($objectMember, $object));

        $resolved = $this->properties->resolveForObject($objectId, 'offset');
        self::assertNotNull($resolved);
        self::assertSame($objectProperty, $resolved['id']);

        self::assertSame([$objectId], array_column($this->objects->forClass($root, true), 'id'));
        self::assertSame([$objectId], array_column($this->objects->forClass($child, false), 'id'));
    }

    public function testCyclesAndDeletionAreGuarded(): void
    {
        $root = $this->classes->create('Sensor');
        $child = $this->classes->create('TemperatureSensor', $root);

        try {
            $this->classes->update($root, 'Sensor', $child);
            self::fail('Цикл наследования должен быть отклонён.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('подкласса', $error->getMessage());
        }

        try {
            $this->classes->delete($root);
            self::fail('Класс с подклассами удалять нельзя.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('подклассы', $error->getMessage());
        }

        $this->objects->create('Kitchen', $child, 0, '');
        try {
            $this->classes->delete($child);
            self::fail('Класс с объектами удалять нельзя.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('объекты', $error->getMessage());
        }
    }

    public function testMovingClassUnderParentWithSameMemberIsRejected(): void
    {
        $left = $this->classes->create('LeftBranch');
        $right = $this->classes->create('RightBranch');
        $this->properties->create($left, 'temperature', '', 'float', false, '');
        $this->properties->create($right, 'temperature', '', 'float', false, '');

        try {
            $this->classes->update($left, 'LeftBranch', $right);
            self::fail('Перенос под родителя с одноимённым свойством должен быть отклонён.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('родительского класса', $error->getMessage());
        }

        $this->classes->update($left, 'LeftBranch', 0);
        self::assertSame(0, $this->classes->find($left)['parent_id']);
    }

    public function testMovingObjectToClassWithSameMemberIsRejected(): void
    {
        $left = $this->classes->create('LeftBranch');
        $right = $this->classes->create('RightBranch');
        $objectId = $this->objects->create('LivingRoom', $left, 0, '');
        $this->properties->create($left, 'humidity', '', 'float', false, '', $objectId);
        $this->properties->create($right, 'humidity', '', 'float', false, '');

        try {
            $this->objects->update($objectId, 'LivingRoom', $right, 0, '');
            self::fail('Перенос объекта к классу с одноимённым свойством должен быть отклонён.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('нового класса', $error->getMessage());
        }
    }

    public function testDeletedNamesCanBeReused(): void
    {
        $classId = $this->classes->create('ReusableClass');
        $this->classes->delete($classId);
        $newClassId = $this->classes->create('ReusableClass');
        self::assertNotSame($classId, $newClassId);

        $objectId = $this->objects->create('ReusableObject', $newClassId, 0, '');
        $this->objects->delete($objectId);
        $newObjectId = $this->objects->create('ReusableObject', $newClassId, 0, '');
        self::assertNotSame($objectId, $newObjectId);
    }

    public function testSystemNamesRequireLatinAndGlobalUniqueness(): void
    {
        try {
            $this->classes->create('Датчик');
            self::fail('Кириллица в системном имени класса должна быть отклонена.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('латиница', $error->getMessage());
        }

        $classId = $this->classes->create('Sensor');
        try {
            $this->classes->create('Sensor');
            self::fail('Повтор класса должен быть отклонён.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('уже существует', $error->getMessage());
        }

        $this->objects->create('Kitchen', $classId, 0, '');
        try {
            $this->objects->create('Kitchen', $classId, 0, '');
            self::fail('Повтор объекта должен быть отклонён.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('уже существует', $error->getMessage());
        }
    }
}
