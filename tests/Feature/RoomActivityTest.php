<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Locations\LocationKind;
use MajorDoMo\Locations\LocationRepository;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Runtime\CodeRunner;
use PHPUnit\Framework\TestCase;

/**
 * Автоматическая модель комнаты и активность по ролям свойств.
 *
 * 2026-10-27 — тест создан вместе с иерархией локаций и таймером активности.
 */
final class RoomActivityTest extends TestCase
{
    use DrainsEvents;

    public function testRoomCreatesRoomsClassFloorSubclassObjectAndDefaults(): void
    {
        $app = $this->boot();
        $locations = $app->container()->make(LocationRepository::class);
        $classes = $app->container()->make(ClassRepository::class);

        $houseId = $locations->create('Дом', 0, 0, LocationKind::HOUSE);
        $floorId = $locations->create('Первый этаж', 0, $houseId, LocationKind::FLOOR);
        $roomId = $locations->create('Спальня', 0, $floorId, LocationKind::ROOM, 600);

        $house = $locations->find($houseId);
        $floor = $locations->find($floorId);
        $room = $locations->find($roomId);
        self::assertSame(LocationKind::HOUSE, $house['kind']);
        self::assertSame($houseId, $floor['parent_id']);
        self::assertSame($floorId, $room['parent_id']);
        self::assertSame(600, $room['activity_timeout']);

        $base = $this->classByTitle($app, 'Rooms');
        self::assertNotNull($base);
        self::assertSame(0, (int)$base['parent_id']);
        self::assertCount(6, $app->container()->make(PropertyRepository::class)->ownForClass((int)$base['id']));
        self::assertCount(2, $app->container()->make(MethodRepository::class)->ownForClass((int)$base['id']));

        $floorClass = $classes->find((int)$floor['class_id']);
        self::assertNotNull($floorClass);
        self::assertSame('Pervyy_etazh', $floorClass['title']);
        self::assertSame((int)$base['id'], (int)$floorClass['parent_id']);
        self::assertSame((int)$floorClass['id'], (int)$room['class_id']);

        $object = $app->container()->make(ObjectRepository::class)->find((int)$room['object_id']);
        self::assertNotNull($object);
        self::assertSame('Spalnya', $object['title']);
        self::assertSame((int)$floorClass['id'], (int)$object['class_id']);
        self::assertSame($roomId, (int)$object['location_id']);

        self::assertSame('600', gg('Spalnya.activityTimeout'));
        self::assertSame('0', gg('Spalnya.isActive'));
        self::assertSame('0', gg('Spalnya.isLight'));
        self::assertSame('0', gg('Spalnya.isHeating'));
        self::assertSame('0', gg('Spalnya.isCondition'));
    }

    public function testSecondRoomWithSameNameGetsSuffix(): void
    {
        $app = $this->boot();
        $locations = $app->container()->make(LocationRepository::class);
        $floorId = $locations->create('Этаж', 0, 0, LocationKind::FLOOR);

        $first = $locations->find($locations->create('Спальня', 0, $floorId, LocationKind::ROOM));
        $second = $locations->find($locations->create('Спальня', 0, $floorId, LocationKind::ROOM));

        self::assertSame('Spalnya', $app->container()->make(ObjectRepository::class)->find((int)$first['object_id'])['title']);
        self::assertSame('Spalnya_2', $app->container()->make(ObjectRepository::class)->find((int)$second['object_id'])['title']);
    }

    public function testRoleChangeActivatesRoomAndExpiryCallsHooks(): void
    {
        $app = $this->boot();
        $locations = $app->container()->make(LocationRepository::class);
        $classes = $app->container()->make(ClassRepository::class);
        $objects = $app->container()->make(ObjectRepository::class);
        $properties = $app->container()->make(PropertyRepository::class);
        $methods = $app->container()->make(MethodRepository::class);

        $floorId = $locations->create('Первый этаж', 0, 0, LocationKind::FLOOR);
        $roomId = $locations->create('Кухня', 0, $floorId, LocationKind::ROOM, 600);

        $base = $this->classByTitle($app, 'Rooms');
        self::assertNotNull($base);
        $properties->create((int)$base['id'], 'marker', '', 'string', false, '');
        $activity = $methods->findForClass((int)$base['id'], 'activity');
        self::assertNotNull($activity);
        $methods->update((int)$activity['id'], (int)$base['id'], 'activity', (string)$activity['description'], "\$this->set('marker', '1');");
        $nonActivity = $methods->findForClass((int)$base['id'], 'nonActivity');
        self::assertNotNull($nonActivity);
        $methods->update((int)$nonActivity['id'], (int)$base['id'], 'nonActivity', (string)$nonActivity['description'], "\$this->set('marker', '0');");

        $lightClass = $classes->create('Light');
        $objects->create('KitchenLight', $lightClass, $roomId, '');
        $properties->create($lightClass, 'state', '', 'bool', false, '', 0, false, 'light');

        self::assertTrue(sg('KitchenLight.state', '1', 'test'));
        $this->drainEvents($app);

        self::assertSame('1', gg('Kukhnya.isActive'));
        self::assertSame('1', gg('Kukhnya.isLight'));
        self::assertSame('0', gg('Kukhnya.isHeating'));
        self::assertSame('1', gg('Kukhnya.marker'));
        self::assertNotSame('', (string)gg('Kukhnya.lastActivityAt'));
        self::assertTrue(roomIsActive('Кухня'));
        self::assertTrue(roomIsLight('Kukhnya'));
        self::assertFalse(roomIsHeating('Кухня'));

        $timer = $app->database()->connection()->table('runtime_timeouts')
            ->where('title', 'room.activity.' . $roomId)
            ->whereNull('executed_at')
            ->whereNull('deleted_at')
            ->first();
        self::assertNotNull($timer);

        // Отключаем свет: флаг снимается, но активность ещё держится таймером.
        self::assertTrue(sg('KitchenLight.state', '0', 'test'));
        $this->drainEvents($app);
        self::assertSame('0', gg('Kukhnya.isLight'));
        self::assertSame('1', gg('Kukhnya.isActive'));

        // Сдвигаем последнюю активность в прошлое и выполняем тот же код,
        // который штатно запускает core-worker timeouts.
        self::assertTrue(sg('Kukhnya.lastActivityAt', date('Y-m-d H:i:s', time() - 3600)));
        $app->container()->make(CodeRunner::class)->run((string)$timer->code);

        self::assertSame('0', gg('Kukhnya.isActive'));
        self::assertSame('0', gg('Kukhnya.marker'));
        self::assertFalse(roomIsActive('Кухня'));
    }

    private function boot(): Application
    {
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'cache' => ['driver' => 'array'],
            'log' => 'php://stderr',
            'session' => new ArraySession(),
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();

        foreach ([
            '2026_10_26_000011_create_locations.php',
            '2026_10_26_000013_create_classes.php',
            '2026_10_26_000014_create_properties.php',
            '2026_10_26_000015_create_methods.php',
            '2026_10_26_000016_create_objects_and_values.php',
            '2026_10_26_000017_create_property_history.php',
            '2026_10_26_000018_create_runtime_timeouts.php',
            '2026_10_26_000019_create_property_events.php',
            '2026_10_27_000001_add_class_hierarchy_and_object_members.php',
            '2026_10_27_000002_create_property_subscriptions.php',
            '2026_10_27_000004_add_runtime_timeouts_claim.php',
            '2026_10_27_000009_add_class_description.php',
            '2026_10_27_000010_add_property_only_new_values.php',
            '2026_10_27_000011_create_property_executions.php',
            '2026_10_27_000012_extend_locations_and_properties.php',
        ] as $name) {
            $migration = require dirname(__DIR__, 2) . '/database/migrations/' . $name;
            $migration($app->database()->connection());
        }

        return $app;
    }

    /**
     * @return array{id:int,title:string,parent_id:int,description:string}|null
     */
    private function classByTitle(Application $app, string $title): ?array
    {
        foreach ($app->container()->make(ClassRepository::class)->all() as $class) {
            if ($class['title'] === $title) {
                return $class;
            }
        }

        return null;
    }
}
