<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Http\Request;
use MajorDoMo\Database\Migrator;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * Раздел «Классы»: дерево, локация у объекта, латинские имена и уникальность.
 *
 * 2026-10-27 — проверяются требования владельца: объекты видны дочерними
 * строками дерева на ПК, на телефоне остаётся панель; имя проверяется сразу
 * в форме и отклоняется сервером; класс не привязан к локации.
 */
final class ClassesUiTest extends TestCase
{
    public function testClassesPageRendersObjectRowsAndMobilePanel(): void
    {
        $app = $this->boot();

        $html = (string)$app->handle(Request::create('/classes', 'GET'))->getContent();

        self::assertStringContainsString('mdClassesTree', $html);
        self::assertStringContainsString('md-classes-object-row', $html);
        self::assertStringContainsString('md-classes-mobile-objects', $html);
        self::assertStringContainsString('data-parent-id="1"', $html);
        self::assertStringContainsString('KitchenLight', $html);
        self::assertStringContainsString('Кухня', $html);
        self::assertStringContainsString('Базовый датчик', $html);
        self::assertStringContainsString('Показано', $html);
    }

    public function testClassFormChecksNameAndHasNoLocation(): void
    {
        $app = $this->boot();

        $create = (string)$app->handle(Request::create('/classes/create', 'GET'))->getContent();
        self::assertStringContainsString('mdNameCheck', $create);
        self::assertStringContainsString('taken', $create);
        self::assertStringContainsString('Sensor', $create);
        self::assertStringNotContainsString('name="location_id"', $create);

        $edit = (string)$app->handle(Request::create('/classes/1/edit', 'GET'))->getContent();
        self::assertStringNotContainsString('name="location_id"', $edit);
        self::assertStringContainsString('Базовый датчик', $edit);
    }

    public function testObjectFormHasLocationAndGlobalNameCheck(): void
    {
        $app = $this->boot();

        $html = (string)$app->handle(Request::create('/objects/create', 'GET'))->getContent();

        self::assertStringContainsString('name="location_id"', $html);
        self::assertStringContainsString('mdNameCheck', $html);
        self::assertStringContainsString('KitchenLight', $html);
        self::assertStringContainsString('Локация принадлежит объекту', $html);
    }

    public function testCyrillicAndDuplicateClassNamesAreRejected(): void
    {
        $app = $this->boot();
        $session = $app->session();

        $cyrillic = $app->handle(Request::create('/classes/create', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Датчик',
        ]));
        self::assertSame(422, $cyrillic->getStatusCode());
        self::assertStringContainsString('латиница', (string)$cyrillic->getContent());

        $duplicate = $app->handle(Request::create('/classes/create', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Sensor',
        ]));
        self::assertSame(422, $duplicate->getStatusCode());
        self::assertStringContainsString('уже существует', (string)$duplicate->getContent());
    }

    public function testObjectAndPropertyNamesAreValidated(): void
    {
        $app = $this->boot();
        $session = $app->session();

        $cyrillicObject = $app->handle(Request::create('/objects/create', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Кухня',
            'class_id' => 1,
            'location_id' => 1,
        ]));
        self::assertSame(422, $cyrillicObject->getStatusCode());
        self::assertStringContainsString('латиница', (string)$cyrillicObject->getContent());

        $duplicateObject = $app->handle(Request::create('/objects/create', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'KitchenLight',
            'class_id' => 1,
            'location_id' => 1,
        ]));
        self::assertSame(422, $duplicateObject->getStatusCode());
        self::assertStringContainsString('уже существует', (string)$duplicateObject->getContent());

        $duplicateProperty = $app->handle(Request::create('/properties/create', 'POST', [
            '_token' => $session->csrfToken(),
            'class_id' => 1,
            'title' => 'temperature',
        ]));
        self::assertSame(422, $duplicateProperty->getStatusCode());
        self::assertStringContainsString('уже объявлено', (string)$duplicateProperty->getContent());

        $childDuplicate = $app->handle(Request::create('/properties/create', 'POST', [
            '_token' => $session->csrfToken(),
            'class_id' => 2,
            'title' => 'temperature',
        ]));
        self::assertSame(422, $childDuplicate->getStatusCode());
        self::assertStringContainsString('уже объявлено', (string)$childDuplicate->getContent());
    }

    public function testMethodNamesAreLatinAndUnique(): void
    {
        $app = $this->boot();
        $session = $app->session();

        $cyrillic = $app->handle(Request::create('/methods/create', 'POST', [
            '_token' => $session->csrfToken(),
            'class_id' => 1,
            'title' => 'обновить',
            'code' => 'return 1;',
        ]));
        self::assertSame(422, $cyrillic->getStatusCode());

        $duplicate = $app->handle(Request::create('/methods/create', 'POST', [
            '_token' => $session->csrfToken(),
            'class_id' => 1,
            'title' => 'refresh',
            'code' => 'return 1;',
        ]));
        self::assertSame(422, $duplicate->getStatusCode());
        self::assertStringContainsString('уже объявлен', (string)$duplicate->getContent());
    }

    public function testLocationPageShowsObjectsOfThatLocationOnly(): void
    {
        $app = $this->boot();

        $html = (string)$app->handle(Request::create('/locations/1/edit', 'GET'))->getContent();

        self::assertStringContainsString('Объекты в этой локации', $html);
        self::assertStringContainsString('KitchenLight', $html);
    }

    public function testPropertyCardShowsBindingsAndHidesHistoryButtonWithoutHistory(): void
    {
        $app = $this->boot();
        $connection = $app->database()->connection();
        $now = date('Y-m-d H:i:s');
        $connection->table('property_subscriptions')->insert([
            'object_id' => 1,
            'property_id' => 1,
            'module' => 'mqtt',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $page = (string)$app->handle(Request::create('/properties/1/edit', 'GET'))->getContent();
        self::assertStringContainsString('Использование', $page);
        self::assertStringContainsString('mqtt', $page);
        self::assertStringContainsString('Повторная запись', $page);

        $objectPage = (string)$app->handle(Request::create('/objects/1/edit', 'GET', ['tab' => 'properties']))->getContent();
        self::assertSame(0, substr_count($objectPage, 'yoyo:name="property-history"'));

        $connection->table('properties')->where('id', 1)->update(['keep_history' => 1, 'updated_at' => $now]);
        $objectPage = (string)$app->handle(Request::create('/objects/1/edit', 'GET', ['tab' => 'properties']))->getContent();
        self::assertSame(1, substr_count($objectPage, 'yoyo:name="property-history"'));
    }

    public function testPropertyFormSavesRepeatPolicy(): void
    {
        $app = $this->boot();
        $session = $app->session();

        $response = $app->handle(Request::create('/properties/1/edit', 'POST', [
            '_token' => $session->csrfToken(),
            'class_id' => 1,
            'object_id' => 0,
            'title' => 'temperature',
            'description' => '',
            'data_type' => 'float',
            'keep_history' => '0',
            'only_new_values' => '1',
            'on_change' => '',
        ]));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(1, (int)$app->database()->connection()->table('properties')->where('id', 1)->value('only_new_values'));
        self::assertSame(0, (int)$app->database()->connection()->table('properties')->where('id', 1)->value('keep_history'));
    }

    private function boot(): Application
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Администратор', 'is_admin' => true]);

        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'cache' => ['driver' => 'array'],
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        (new Migrator($app->database(), $app->root() . '/database/migrations'))->migrate();
        SessionFixture::authenticate($app);

        $connection = $app->database()->connection();
        $now = date('Y-m-d H:i:s');
        $connection->table('classes')->insert([
            ['id' => 1, 'title' => 'Sensor', 'parent_id' => 0, 'description' => 'Базовый датчик', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'title' => 'TemperatureSensor', 'parent_id' => 1, 'description' => '', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('locations')->insert([
            ['id' => 1, 'title' => 'Кухня', 'priority' => 10, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('objects')->insert([
            ['id' => 1, 'title' => 'KitchenLight', 'class_id' => 2, 'location_id' => 1, 'description' => 'Свет на кухне', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('properties')->insert([
            ['id' => 1, 'class_id' => 1, 'object_id' => 0, 'title' => 'temperature', 'data_type' => 'float', 'keep_history' => 0, 'on_change' => '', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('methods')->insert([
            ['id' => 1, 'class_id' => 1, 'object_id' => 0, 'title' => 'refresh', 'description' => '', 'code' => 'return 1;', 'created_at' => $now, 'updated_at' => $now],
        ]);

        return $app;
    }
}
