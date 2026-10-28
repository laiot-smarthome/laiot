<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Modules\ModuleInstaller;
use MajorDoMo\Modules\ModuleManifest;
use MajorDoMo\Modules\ModuleSettings;
use PHPUnit\Framework\TestCase;

/**
 * Настройки SDK-модуля: схема в манифесте, значения в реестре, общая форма.
 *
 * Модуль не создаёт свою таблицу настроек: типы проверяет `ModuleSettings`,
 * а экран `/admin/modules/<id>/settings` строится по той же схеме.
 *
 * 2026-10-28 — выбор подписок перенесён в штатное модальное окно.
 * 2026-10-27 — тест создан вместе с настройками модулей из манифеста.
 */
final class ModuleSettingsTest extends TestCase
{
    public function testManifestNormalizesSettingsSchema(): void
    {
        $manifest = $this->manifest([
            'settings' => [
                'greeting' => ['title' => 'Приветствие', 'type' => 'string', 'default' => 'Привет'],
                'limit' => ['title' => 'Лимит', 'type' => 'int', 'default' => 10],
                'enabled' => ['title' => 'Включено', 'type' => 'bool', 'default' => true],
                'mode' => [
                    'title' => 'Режим',
                    'type' => 'select',
                    'options' => ['simple' => 'Простой', 'advanced' => 'Расширенный'],
                ],
            ],
        ]);

        $schema = $manifest->settingsSchema();
        self::assertSame('Привет', $schema['greeting']['default']);
        self::assertSame(10, $schema['limit']['default']);
        self::assertTrue($schema['enabled']['default']);
        self::assertSame('simple', $schema['mode']['default']);

        $settings = ModuleSettings::fromStored($schema, ['limit' => 5, 'mode' => 'advanced']);
        self::assertSame('Привет', $settings->get('greeting'));
        self::assertSame(5, $settings->get('limit'));
        self::assertSame('advanced', $settings->get('mode'));
        self::assertTrue($settings->get('enabled'));
        self::assertNull($settings->get('unknown'));
    }

    public function testManifestRejectsInvalidSettings(): void
    {
        foreach ([
            ['settings' => 'строка'],
            ['settings' => [['title' => 'Поле']]],
            ['settings' => ['Host' => ['title' => 'Хост']]],
            ['settings' => ['host' => ['title' => '']]],
            ['settings' => ['host' => ['title' => 'Хост', 'type' => 'float']]],
            ['settings' => ['host' => ['title' => 'Хост', 'type' => 'bool', 'default' => 1]]],
            ['settings' => ['host' => ['title' => 'Хост', 'type' => 'int', 'default' => '5']]],
            ['settings' => ['host' => ['title' => 'Хост', 'type' => 'select', 'options' => []]]],
            ['settings' => ['host' => ['title' => 'Хост', 'type' => 'select', 'options' => ['a' => 'А'], 'default' => 'b']]],
        ] as $invalid) {
            try {
                $this->manifest($invalid);
                self::fail('Ожидалась ошибка схемы настроек');
            } catch (InvalidArgumentException $error) {
                self::assertNotEmpty($error->getMessage());
            }
        }
    }

    public function testCastKeepsDeclaredTypesAndRejectsBadValues(): void
    {
        $schema = $this->manifest([
            'settings' => [
                'host' => ['title' => 'Хост', 'type' => 'string', 'default' => 'localhost'],
                'port' => ['title' => 'Порт', 'type' => 'int', 'default' => 1883],
                'tls' => ['title' => 'TLS', 'type' => 'bool', 'default' => false],
                'mode' => ['title' => 'Режим', 'type' => 'select', 'options' => ['a' => 'А', 'b' => 'Б']],
            ],
        ])->settingsSchema();

        $values = ModuleSettings::cast($schema, [
            'host' => ' broker ',
            'port' => '1884',
            'tls' => '1',
            'mode' => 'b',
            'ignored' => 'x',
        ]);
        self::assertSame('broker', $values['host']);
        self::assertSame(1884, $values['port']);
        self::assertTrue($values['tls']);
        self::assertSame('b', $values['mode']);
        self::assertArrayNotHasKey('ignored', $values);

        $defaults = ModuleSettings::cast($schema, ['port' => '', 'mode' => 'a']);
        self::assertSame(1883, $defaults['port']);
        self::assertFalse($defaults['tls']);

        try {
            ModuleSettings::cast($schema, ['port' => 'не число']);
            self::fail('Ожидалась ошибка целого числа');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('целое', $error->getMessage());
        }
        try {
            ModuleSettings::cast($schema, ['port' => '1', 'mode' => 'nope']);
            self::fail('Ожидалась ошибка значения select');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('недопустимое', $error->getMessage());
        }
    }

    public function testInstalledModuleReadsDefaultsThroughContext(): void
    {
        $app = $this->boot();
        $app->container()->make(ModuleInstaller::class)->install('example_sdk');

        $settings = $app->container()->make(\MajorDoMo\Modules\ModuleManager::class)
            ->context('example_sdk')->settings();

        self::assertSame('Привет', $settings->get('greeting'));
        self::assertSame(10, $settings->get('notes_limit'));
        self::assertSame('simple', $settings->get('mode'));
        self::assertTrue($settings->get('show_block'));
    }

    public function testSettingsPageSavesValuesAndShowsErrors(): void
    {
        $app = $this->boot();
        $app->container()->make(ModuleInstaller::class)->install('example_sdk');
        $token = $app->session()->csrfToken();

        $page = $app->handle(Request::create('/admin/modules/example_sdk/settings', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        $html = (string)$page->getContent();
        self::assertStringContainsString('Приветствие', $html);
        self::assertStringContainsString('Сколько заметок показывать', $html);
        self::assertStringContainsString('Подписки на свойства', $html);

        $saved = $app->handle(Request::create('/admin/modules/example_sdk/settings', 'POST', [
            '_token' => $token,
            'greeting' => 'Здравствуйте',
            'notes_limit' => '25',
            'mode' => 'advanced',
        ]));
        self::assertSame(302, $saved->getStatusCode());
        self::assertSame('/admin/modules/example_sdk/settings', $saved->headers->get('Location'));

        $stored = $app->container()->make(\MajorDoMo\Modules\ModuleManager::class)->registry()->settings('example_sdk');
        self::assertSame('Здравствуйте', $stored['greeting']);
        self::assertSame(25, $stored['notes_limit']);
        self::assertSame('advanced', $stored['mode']);
        self::assertFalse($stored['show_block']);

        $invalid = $app->handle(Request::create('/admin/modules/example_sdk/settings', 'POST', [
            '_token' => $token,
            'greeting' => 'x',
            'notes_limit' => 'много',
            'mode' => 'simple',
        ]));
        self::assertSame(302, $invalid->getStatusCode());
        $location = (string)$invalid->headers->get('Location');
        self::assertSame('/admin/modules/example_sdk/settings', $location);

        $errorPage = $app->handle(Request::create($location, 'GET'));
        self::assertSame(200, $errorPage->getStatusCode());
        self::assertStringContainsString('целое число', (string)$errorPage->getContent());

        $noToken = $app->handle(Request::create('/admin/modules/example_sdk/settings', 'POST', [
            'greeting' => 'x',
            'notes_limit' => '1',
            'mode' => 'simple',
        ]));
        self::assertSame(403, $noToken->getStatusCode());
    }

    public function testSettingsPageRequiresInstalledModule(): void
    {
        $app = $this->boot();

        $response = $app->handle(Request::create('/admin/modules/example_sdk/settings', 'GET'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/modules', $response->headers->get('Location'));
    }

    public function testSubscriptionsPageSavesSelection(): void
    {
        $app = $this->boot();
        $app->container()->make(ModuleInstaller::class)->install('example_sdk');
        $this->seedHouse($app);
        $token = $app->session()->csrfToken();

        $saved = $app->handle(Request::create('/admin/modules/example_sdk/subscriptions', 'POST', [
            '_token' => $token,
            'object_id' => 1,
            'properties' => [1, 2],
        ]));
        self::assertSame(302, $saved->getStatusCode());
        self::assertSame('/admin/modules/example_sdk/settings', $saved->headers->get('Location'));

        $rows = $app->database()->connection()->table('property_subscriptions')
            ->where('module', 'example_sdk')->orderBy('property_id')->pluck('property_id')->all();
        self::assertSame([1, 2], array_map('intval', $rows));

        $page = $app->handle(Request::create('/admin/modules/example_sdk/settings?object_id=1', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        $html = (string)$page->getContent();
        self::assertStringContainsString('temperature', $html);
        self::assertStringContainsString('id="module-subscriptions"', $html);
        self::assertStringContainsString('Настроить подписки', $html);

        $removed = $app->handle(Request::create('/admin/modules/example_sdk/subscriptions', 'POST', [
            '_token' => $token,
            'object_id' => 1,
            'properties' => [2],
        ]));
        self::assertSame(302, $removed->getStatusCode());
        self::assertSame([2], array_map('intval', $app->database()->connection()
            ->table('property_subscriptions')->where('module', 'example_sdk')->pluck('property_id')->all()));

        $foreign = $app->handle(Request::create('/admin/modules/example_sdk/subscriptions', 'POST', [
            '_token' => $token,
            'object_id' => 1,
            'properties' => [3],
        ]));
        self::assertSame(302, $foreign->getStatusCode());
        self::assertSame('/admin/modules/example_sdk/settings', $foreign->headers->get('Location'));
    }

    private function seedHouse(Application $app): void
    {
        $connection = $app->database()->connection();
        $now = date('Y-m-d H:i:s');

        $connection->table('classes')->insert([
            ['id' => 1, 'title' => 'Датчик', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'title' => 'Другое', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('objects')->insert([
            ['id' => 1, 'title' => 'Кухня', 'class_id' => 1, 'location_id' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('properties')->insert([
            ['id' => 1, 'class_id' => 1, 'object_id' => 0, 'title' => 'temperature', 'data_type' => 'float', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'class_id' => 1, 'object_id' => 0, 'title' => 'state', 'data_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'class_id' => 2, 'object_id' => 0, 'title' => 'other', 'data_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function manifest(array $extra): ModuleManifest
    {
        return ModuleManifest::fromArray($extra + [
            'id' => 'demo',
            'type' => 'module',
            'namespace' => 'Demo\\Module\\',
            'version' => '1.0.0',
        ], '/tmp/demo', 'module');
    }

    private function boot(): Application
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $session,
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        $this->createModuleTables($app);
        SessionFixture::authenticate($app);

        return $app;
    }

    private function createModuleTables(Application $app): void
    {
        $schema = $app->database()->schema();
        if (!$schema->hasTable('module_registry')) {
            $schema->create('module_registry', static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('module_id', 64)->unique();
                $table->string('type', 16)->default('module');
                $table->string('name', 190);
                $table->string('version', 32);
                $table->string('path', 255);
                $table->boolean('enabled')->default(true);
                $table->string('manifest_hash', 64);
                $table->text('settings')->nullable();
                $table->dateTime('installed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (!$schema->hasTable('module_migrations')) {
            $schema->create('module_migrations', static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('module_id', 64);
                $table->string('migration', 190);
                $table->dateTime('applied_at');
                $table->unique(['module_id', 'migration']);
            });
        }
        if (!$schema->hasTable('classes')) {
            $schema->create('classes', static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title');
                $table->integer('parent_id')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (!$schema->hasTable('objects')) {
            $schema->create('objects', static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title');
                $table->integer('class_id')->default(0);
                $table->integer('location_id')->default(0);
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (!$schema->hasTable('properties')) {
            $schema->create('properties', static function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('class_id');
                $table->integer('object_id')->default(0);
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('data_type')->default('string');
                $table->boolean('keep_history')->default(false);
                $table->string('on_change')->default('');
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (!$schema->hasTable('property_subscriptions')) {
            $schema->create('property_subscriptions', static function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('object_id');
                $table->integer('property_id');
                $table->string('module', 64);
                $table->timestamps();
                $table->unique(['object_id', 'property_id', 'module']);
            });
        }
    }
}
