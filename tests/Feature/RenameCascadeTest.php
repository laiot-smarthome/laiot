<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Database\Migrator;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Rename\CodeReferenceRewriter;
use MajorDoMo\Rename\NameReferenceUpdater;
use MajorDoMo\Runtime\PhpSyntaxValidator;
use PHPUnit\Framework\TestCase;

/**
 * Каскадное переименование имён.
 *
 * 2026-10-27 — проверяется, что после переименования объекта, свойства,
 * метода и класса обновляются ссылки в сценариях, методах, настройках
 * модулей и значениях-ссылках, а не только строка справочника.
 */
final class RenameCascadeTest extends TestCase
{
    public function testObjectRenameUpdatesUserCodeAndSettings(): void
    {
        $app = $this->boot();
        $connection = $app->database()->connection();
        $objects = $app->container()->make(ObjectRepository::class);

        $objects->update(1, 'HallLight', 1, 0, '');

        self::assertSame('HallLight', $connection->table('objects')->where('id', 1)->value('title'));
        self::assertStringContainsString("sg('HallLight.temperature'", $this->script($connection, 1));
        self::assertStringContainsString("callMethod('HallLight', 'refresh')", $this->script($connection, 1));
        self::assertStringContainsString("getObject('HallLight')", $this->script($connection, 1));
        self::assertStringContainsString("\$this->get('temperature')", $this->method($connection, 1));
        self::assertStringContainsString("sg('HallLight.temperature'", $this->method($connection, 1));
        self::assertStringContainsString('HallLight', (string)$connection->table('settings')->where('name', 'LINKED_OBJECT')->value('value'));
        self::assertStringContainsString('HallLight', (string)$connection->table('property_values')->where('object_id', 2)->value('value'));
        $settings = (string)$connection->table('module_registry')->where('module_id', 'demo')->value('settings');
        self::assertStringContainsString('HallLight.temperature', $settings);
    }

    public function testPropertyRenameUpdatesCallsAndModuleSettings(): void
    {
        $app = $this->boot();
        $connection = $app->database()->connection();
        $properties = $app->container()->make(PropertyRepository::class);

        $properties->update(1, 1, 'temp', '', 'float', false, 'refresh', 0);

        self::assertSame('temp', $connection->table('properties')->where('id', 1)->value('title'));
        self::assertStringContainsString("sg('KitchenLight.temp'", $this->script($connection, 1));
        self::assertStringContainsString("\$this->get('temp')", $this->method($connection, 1));
        self::assertStringContainsString('KitchenLight.temp', (string)$connection->table('module_registry')->where('module_id', 'demo')->value('settings'));
        self::assertSame('refresh', $connection->table('properties')->where('id', 1)->value('on_change'));
    }

    public function testMethodRenameUpdatesOnChangeAndCalls(): void
    {
        $app = $this->boot();
        $connection = $app->database()->connection();
        $methods = $app->container()->make(MethodRepository::class);

        $methods->update(1, 1, 'reload', '', "\$this->call('refresh');\n\$this->get('temperature');", 0);

        self::assertSame('reload', $connection->table('methods')->where('id', 1)->value('title'));
        self::assertSame('reload', $connection->table('properties')->where('id', 1)->value('on_change'));
        self::assertStringContainsString("callMethod('KitchenLight', 'reload')", $this->script($connection, 1));
        self::assertStringContainsString("cm('KitchenLight.reload')", $this->script($connection, 1));
        self::assertStringContainsString("\$this->call('reload')", $this->method($connection, 1));
    }

    public function testClassRenameUpdatesExactCodeAndSettingsReferences(): void
    {
        $app = $this->boot();
        $connection = $app->database()->connection();
        $classes = $app->container()->make(ClassRepository::class);

        $classes->update(1, 'Device', 0, 'Базовый датчик');

        self::assertSame('Device', $connection->table('classes')->where('id', 1)->value('title'));
        self::assertStringContainsString("getObject('Device')", $this->script($connection, 2));
        self::assertStringContainsString('Device', (string)$connection->table('settings')->where('name', 'DEVICE_CLASS')->value('value'));
        self::assertStringContainsString('Device', (string)$connection->table('module_registry')->where('module_id', 'demo')->value('settings'));
    }

    public function testModuleFilesAreRewrittenAndFlushed(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mydomo-rename-' . bin2hex(random_bytes(4));
        mkdir($root . '/config', 0777, true);
        mkdir($root . '/modules/demo', 0777, true);
        file_put_contents($root . '/config/app.php', "<?php\nreturn [];\n");
        file_put_contents(
            $root . '/modules/demo/code.php',
            "<?php\nreturn ['object' => 'KitchenLight'];\n"
        );
        file_put_contents(
            $root . '/modules/demo/module.json',
            json_encode(['object' => 'KitchenLight', 'label' => 'KitchenLight'], JSON_UNESCAPED_UNICODE)
        );

        try {
            $app = new Application($root);
            $database = new DatabaseManager(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
            $updater = new NameReferenceUpdater(
                $database,
                $app,
                new CodeReferenceRewriter(),
                new PhpSyntaxValidator()
            );
            $updater->objectRenamed(1, 'KitchenLight', 'HallLight');
            self::assertGreaterThanOrEqual(3, $updater->changes());
            $updater->flushModuleFiles();

            self::assertStringContainsString('HallLight', (string)file_get_contents($root . '/modules/demo/code.php'));
            $json = json_decode((string)file_get_contents($root . '/modules/demo/module.json'), true);
            self::assertSame('HallLight', $json['object'] ?? null);
            self::assertSame('HallLight', $json['label'] ?? null);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testClassRenameDoesNotRewriteAnObjectWithSameName(): void
    {
        $app = $this->boot();
        $connection = $app->database()->connection();
        $now = date('Y-m-d H:i:s');
        $connection->table('objects')->insert([
            'id' => 3, 'title' => 'Sensor', 'class_id' => 1, 'location_id' => 0,
            'description' => '', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $classes = $app->container()->make(ClassRepository::class);
        $classes->update(1, 'Device', 0, '');

        self::assertSame('Device', $connection->table('classes')->where('id', 1)->value('title'));
        self::assertSame("getObject('Sensor');", $this->script($connection, 2));
        self::assertSame('Sensor', $connection->table('objects')->where('id', 3)->value('title'));
    }

    public function testHttpRenameShowsUpdatedReferencesNotice(): void
    {
        $app = $this->boot();
        $session = $app->session();
        $response = $app->handle(\Illuminate\Http\Request::create('/objects/1/edit', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'HallLight',
            'class_id' => 1,
            'location_id' => 0,
            'description' => '',
        ]));
        self::assertSame(302, $response->getStatusCode());

        $page = (string)$app->handle(\Illuminate\Http\Request::create('/objects/1/edit', 'GET'))->getContent();
        self::assertStringContainsString('HallLight', $page);
        self::assertStringContainsString('Обновлено ссылок', $page);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($path);
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

        // Фиксированные id этой модели не должны пересекаться с начальным Users.
        foreach (['methods', 'properties', 'classes'] as $table) {
            $app->database()->connection()->table($table)->delete();
        }

        $connection = $app->database()->connection();
        $now = date('Y-m-d H:i:s');
        $connection->table('classes')->insert([
            ['id' => 1, 'title' => 'Sensor', 'parent_id' => 0, 'description' => 'Базовый датчик', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('objects')->insert([
            ['id' => 1, 'title' => 'KitchenLight', 'class_id' => 1, 'location_id' => 0, 'description' => '', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'title' => 'LivingRoomLight', 'class_id' => 1, 'location_id' => 0, 'description' => '', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('properties')->insert([
            ['id' => 1, 'class_id' => 1, 'object_id' => 0, 'title' => 'temperature', 'data_type' => 'float', 'keep_history' => 0, 'on_change' => 'refresh', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('methods')->insert([
            ['id' => 1, 'class_id' => 1, 'object_id' => 0, 'title' => 'refresh', 'description' => '', 'code' => "\$this->get('temperature');\n\$this->call('refresh');\nsg('KitchenLight.temperature', 1);", 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('scripts')->insert([
            'id' => 1,
            'title' => 'Renamed refs',
            'code' => "sg('KitchenLight.temperature', 23.5);\n"
                . "callMethod('KitchenLight', 'refresh');\n"
                . "cm('KitchenLight.refresh');\n"
                . "getObject('KitchenLight');",
            'description' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $connection->table('scripts')->insert([
            'id' => 2,
            'title' => 'Class ref',
            'code' => "getObject('Sensor');",
            'description' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $connection->table('settings')->insert([
            ['name' => 'LINKED_OBJECT', 'value' => 'KitchenLight', 'title' => 'Объект', 'type' => 'text', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'DEVICE_CLASS', 'value' => 'Sensor', 'title' => 'Класс', 'type' => 'text', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('property_values')->insert([
            ['object_id' => 2, 'property_id' => 1, 'value' => 'KitchenLight', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('module_registry')->insert([
            'module_id' => 'demo',
            'type' => 'module',
            'name' => 'Demo',
            'version' => '1.0.0',
            'path' => 'modules/demo',
            'enabled' => 1,
            'manifest_hash' => 'hash',
            'settings' => json_encode([
                'linked' => 'KitchenLight.temperature',
                'property' => 'temperature',
                'method' => 'refresh',
                'class' => 'Sensor',
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $app;
    }

    private function script(\Illuminate\Database\Connection $connection, int $id): string
    {
        return (string)$connection->table('scripts')->where('id', $id)->value('code');
    }

    private function method(\Illuminate\Database\Connection $connection, int $id): string
    {
        return (string)$connection->table('methods')->where('id', $id)->value('code');
    }
}
