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
 * 2026-10-27 — проверяются подклассы без ограничения глубины, переопределение
 * свойств/методов, объектные члены и защита от циклов.
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
            '2026_10_26_000013_create_classes.php',
            '2026_10_26_000014_create_properties.php',
            '2026_10_26_000015_create_methods.php',
            '2026_10_26_000016_create_objects_and_values.php',
            '2026_10_27_000001_add_class_hierarchy_and_object_members.php',
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
        $root = $this->classes->create('Датчик');
        $child = $this->classes->create('Датчик температуры', $root);
        $grandchild = $this->classes->create('Датчик на улице', $child);

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
    }

    public function testSubclassInheritsAndOverridesPropertiesAndMethods(): void
    {
        $root = $this->classes->create('Датчик');
        $child = $this->classes->create('Датчик температуры', $root);
        $grandchild = $this->classes->create('Уличный датчик', $child);

        $this->properties->create($root, 'temperature', '', 'float', true, '');
        $this->properties->create($root, 'battery', '', 'int', false, '');
        $this->properties->create($child, 'humidity', '', 'float', false, '');
        $this->methods->create($root, 'refresh', '', 'return 1;');
        $this->methods->create($child, 'refresh', '', 'return 2;');

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
        self::assertCount(1, $methods);
        self::assertSame('refresh', $methods[0]['title']);
        self::assertSame($child, $methods[0]['owner_class_id'], 'Ближайший подкласс переопределяет метод.');
        self::assertSame('return 2;', $this->methods->findForClass($grandchild, 'refresh')['code'] ?? null);
    }

    public function testObjectPropertiesAndSubclassObjects(): void
    {
        $root = $this->classes->create('Датчик');
        $child = $this->classes->create('Датчик температуры', $root);
        $rootProperty = $this->properties->create($root, 'temperature', '', 'float', false, '');
        $objectId = $this->objects->create('Гостиная', $child, 0, '');
        $objectProperty = $this->properties->create($child, 'temperature', '', 'float', false, '', $objectId);

        $object = $this->objects->find($objectId);
        self::assertNotNull($object);
        $rootMember = $this->properties->find($rootProperty);
        $objectMember = $this->properties->find($objectProperty);
        self::assertNotNull($rootMember);
        self::assertNotNull($objectMember);
        self::assertFalse($this->properties->isApplicableToObject($rootMember, $object));
        self::assertTrue($this->properties->isApplicableToObject($objectMember, $object));

        $resolved = $this->properties->resolveForObject($objectId, 'temperature');
        self::assertNotNull($resolved);
        self::assertSame($objectProperty, $resolved['id']);

        self::assertSame([$objectId], array_column($this->objects->forClass($root, true), 'id'));
        self::assertSame([$objectId], array_column($this->objects->forClass($child, false), 'id'));
    }

    public function testCyclesAndDeletionAreGuarded(): void
    {
        $root = $this->classes->create('Датчик');
        $child = $this->classes->create('Датчик температуры', $root);

        try {
            $this->classes->update($root, 'Датчик', $child);
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

        $this->objects->create('Кухня', $child, 0, '');
        try {
            $this->classes->delete($child);
            self::fail('Класс с объектами удалять нельзя.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('объекты', $error->getMessage());
        }
    }
}
