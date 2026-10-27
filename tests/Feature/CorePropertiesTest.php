<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 * 2026-10-27 — роль в комнате проверяется по локации выбранного объекта:
 * у класса и у объекта в доме/этаже выбор заблокирован.
 */
final class CorePropertiesTest extends TestCase
{
    public function testClassPropertyCannotGetRoomRoleAndShowsDisabledSelect(): void
    {
        [$app, $session] = $this->boot();

        $create = $app->handle(Request::create('/properties/create', 'POST', [
            '_token' => $session->csrfToken(),
            'class_id' => 1,
            'title' => 'temperature',
            'data_type' => 'float',
            'room_role' => 'sensor',
        ]));
        self::assertSame(302, $create->getStatusCode());
        self::assertSame('temperature', $app->database()->connection()->table('properties')->value('title'));
        self::assertSame('', $app->database()->connection()->table('properties')->value('room_role'));

        $page = $app->handle(Request::create('/properties/create?class_id=1', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        $html = (string)$page->getContent();
        self::assertStringContainsString('Роль в комнате доступна только у свойства объекта', $html);
        self::assertStringContainsString('Почему роль недоступна', $html);
        self::assertStringContainsString('disabled', $html);
    }

    public function testObjectPropertyInRoomCanGetRole(): void
    {
        [$app, $session] = $this->boot();

        $create = $app->handle(Request::create('/properties/create', 'POST', [
            '_token' => $session->csrfToken(),
            'class_id' => 1,
            'object_id' => 1,
            'title' => 'state',
            'data_type' => 'bool',
            'room_role' => 'light',
        ]));
        self::assertSame(302, $create->getStatusCode());
        self::assertSame('light', $app->database()->connection()->table('properties')->value('room_role'));

        $page = $app->handle(Request::create('/properties/create?class_id=1&object_id=1', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        $html = (string)$page->getContent();
        self::assertStringContainsString('комнаты «Кухня»', $html);
        self::assertStringNotContainsString('Почему роль недоступна', $html);
    }

    public function testObjectOutsideRoomKeepsRoleBlocked(): void
    {
        [$app, $session] = $this->boot();

        $create = $app->handle(Request::create('/properties/create', 'POST', [
            '_token' => $session->csrfToken(),
            'class_id' => 1,
            'object_id' => 2,
            'title' => 'state',
            'data_type' => 'bool',
            'room_role' => 'heating',
        ]));
        self::assertSame(302, $create->getStatusCode());
        self::assertSame('', $app->database()->connection()->table('properties')->value('room_role'));

        $page = $app->handle(Request::create('/properties/create?class_id=1&object_id=2', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('а роль в комнате доступна только для объекта в комнате', (string)$page->getContent());
    }

    public function testDisabledFieldDoesNotEraseExistingRole(): void
    {
        [$app, $session] = $this->boot();
        $now = date('Y-m-d H:i:s');
        // Роль могла быть записана до ограничения или через API.
        $id = (int)$app->database()->connection()->table('properties')->insertGetId([
            'class_id' => 1,
            'object_id' => 0,
            'title' => 'legacyRole',
            'description' => '',
            'data_type' => 'bool',
            'keep_history' => 0,
            'on_change' => '',
            'room_role' => 'sensor',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $update = $app->handle(Request::create('/properties/' . $id . '/edit', 'POST', [
            '_token' => $session->csrfToken(),
            'class_id' => 1,
            'object_id' => 0,
            'title' => 'legacyRole',
            'data_type' => 'bool',
        ]));
        self::assertSame(302, $update->getStatusCode());
        self::assertSame('sensor', $app->database()->connection()->table('properties')->where('id', $id)->value('room_role'));
    }

    /**
     * @return array{0:Application,1:ArraySession}
     */
    private function boot(): array
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
        $schema = $app->database()->schema();
        $schema->create('classes', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->integer('parent_id')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('properties', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('class_id')->default(0);
            $table->integer('object_id')->default(0);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('data_type')->default('string');
            $table->boolean('keep_history')->default(false);
            $table->boolean('only_new_values')->default(false);
            $table->string('on_change')->default('');
            $table->string('room_role')->default('');
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('methods', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('class_id')->default(0);
            $table->integer('object_id')->default(0);
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('locations', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->integer('priority')->default(0);
            $table->integer('parent_id')->default(0);
            $table->string('kind', 16)->default('room');
            $table->integer('class_id')->default(0);
            $table->integer('object_id')->default(0);
            $table->integer('activity_timeout')->default(900);
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('objects', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->integer('class_id')->default(0);
            $table->integer('location_id')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $now = date('Y-m-d H:i:s');
        $app->database()->connection()->table('classes')->insert([
            'id' => 1, 'title' => 'Sensor', 'parent_id' => 0, 'description' => 'Датчик',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $app->database()->connection()->table('locations')->insert([
            [
                'id' => 1, 'title' => 'Кухня', 'priority' => 0, 'parent_id' => 0, 'kind' => 'room',
                'class_id' => 0, 'object_id' => 0, 'activity_timeout' => 900,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => 2, 'title' => 'Дом', 'priority' => 0, 'parent_id' => 0, 'kind' => 'house',
                'class_id' => 0, 'object_id' => 0, 'activity_timeout' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
        $app->database()->connection()->table('objects')->insert([
            [
                'id' => 1, 'title' => 'KitchenSensor', 'class_id' => 1, 'location_id' => 1,
                'description' => 'Датчик на кухне', 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => 2, 'title' => 'OutdoorSensor', 'class_id' => 1, 'location_id' => 2,
                'description' => 'Датчик дома', 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
        SessionFixture::authenticate($app);

        return [$app, $session];
    }
}
