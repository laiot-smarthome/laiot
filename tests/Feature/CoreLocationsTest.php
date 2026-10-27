<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 * 2026-10-27 — проверяется иерархия дом → этаж → комната, авто-модель
 * комнаты, переименование объекта и запрет удаления родителя.
 */
final class CoreLocationsTest extends TestCase
{
    public function testLocationHierarchyRoomModelAndDelete(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'cache' => ['driver' => 'array'],
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        foreach ($this->migrations() as $name) {
            $migration = require dirname(__DIR__, 2) . '/database/migrations/' . $name;
            $migration($app->database()->connection());
        }

        SessionFixture::authenticate($app);

        $page = $app->handle(Request::create('/locations', 'GET'));
        self::assertSame(200, $page->getStatusCode());

        $house = $this->create($app, $session, ['title' => 'Дом', 'kind' => 'house', 'parent_id' => '0']);
        $floor = $this->create($app, $session, ['title' => 'Первый этаж', 'kind' => 'floor', 'parent_id' => (string)$house]);
        $room = $this->create($app, $session, [
            'title' => 'Гостиная',
            'kind' => 'room',
            'parent_id' => (string)$floor,
            'activity_timeout' => '300',
        ]);

        $row = $app->database()->connection()->table('locations')->where('id', $room)->first();
        self::assertNotNull($row);
        self::assertSame('room', $row->kind);
        self::assertSame($floor, (int)$row->parent_id);
        self::assertSame(300, (int)$row->activity_timeout);
        self::assertGreaterThan(0, (int)$row->object_id);
        self::assertGreaterThan(0, (int)$row->class_id);

        $roomsClass = $app->database()->connection()->table('classes')->where('title', 'Rooms')->first();
        self::assertNotNull($roomsClass);
        $floorClass = $app->database()->connection()->table('classes')->where('id', (int)$row->class_id)->first();
        self::assertNotNull($floorClass);
        self::assertSame((int)$roomsClass->id, (int)$floorClass->parent_id);
        $object = $app->database()->connection()->table('objects')->where('id', (int)$row->object_id)->first();
        self::assertNotNull($object);
        self::assertSame('Gostinaya', $object->title);
        self::assertSame($room, (int)$object->location_id);
        self::assertSame('300', gg('Gostinaya.activityTimeout'));

        $list = $app->handle(Request::create('/locations', 'GET'));
        self::assertSame(200, $list->getStatusCode());
        self::assertStringContainsString('Гостиная', (string)$list->getContent());

        $editPage = $app->handle(Request::create('/locations/' . $room . '/edit', 'GET'));
        self::assertSame(200, $editPage->getStatusCode());
        self::assertStringContainsString('Модель комнаты', (string)$editPage->getContent());
        self::assertStringContainsString('Gostinaya', (string)$editPage->getContent());

        $edit = $app->handle(Request::create('/locations/' . $room . '/edit', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Зал',
            'priority' => '20',
            'activity_timeout' => '300',
        ]));
        self::assertSame(302, $edit->getStatusCode());
        self::assertSame('Зал', $app->database()->connection()->table('locations')->where('id', $room)->value('title'));
        self::assertSame('Zal', $app->database()->connection()->table('objects')->where('id', (int)$row->object_id)->value('title'));

        // Дом с вложенными локациями удалить нельзя.
        $blocked = $app->handle(Request::create('/locations/' . $house . '/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $blocked->getStatusCode());
        self::assertNull($app->database()->connection()->table('locations')->where('id', $house)->value('deleted_at'));

        $delete = $app->handle(Request::create('/locations/' . $room . '/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNotNull($app->database()->connection()->table('locations')->where('id', $room)->value('deleted_at'));
        self::assertNull($app->database()->connection()->table('objects')->where('id', (int)$row->object_id)->whereNull('deleted_at')->first());

        // Пустой этаж удаляется вместе со своим классом, дом — после детей.
        $deleteFloor = $app->handle(Request::create('/locations/' . $floor . '/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $deleteFloor->getStatusCode());
        self::assertNotNull($app->database()->connection()->table('classes')->where('id', (int)$row->class_id)->value('deleted_at'));

        $deleteHouse = $app->handle(Request::create('/locations/' . $house . '/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $deleteHouse->getStatusCode());
        self::assertNotNull($app->database()->connection()->table('locations')->where('id', $house)->value('deleted_at'));
    }

    /**
     * @param array<string,string> $fields
     */
    private function create(Application $app, ArraySession $session, array $fields): int
    {
        $response = $app->handle(Request::create('/locations/create', 'POST', [
            '_token' => $session->csrfToken(),
            'priority' => '0',
        ] + $fields));
        self::assertSame(302, $response->getStatusCode());
        $location = (string)$response->headers->get('Location');
        self::assertMatchesRegularExpression('#^/locations/(\d+)/edit$#', $location);

        return (int)substr($location, strlen('/locations/'), -strlen('/edit'));
    }

    /**
     * @return list<string>
     */
    private function migrations(): array
    {
        return [
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
        ];
    }
}
