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
use PHPUnit\Framework\TestCase;

/**
 * MyDoMo SDK: манифест, реестр, install/enable/disable/remove, миграции и
 * подключение установленного стороннего модуля.
 */
final class ModuleSdkTest extends TestCase
{
    public function testManifestValidation(): void
    {
        $manifest = ModuleManifest::fromArray([
            'id' => 'demo_module',
            'type' => 'module',
            'version' => '1.2.3',
            'namespace' => 'Demo\\Module\\',
            'requires' => ['mydomo' => '>=2.0.0-dev', 'php' => '>=8.2'],
            'storage' => ['driver' => 'mysql', 'tables' => ['notes']],
        ], '/tmp/demo_module', 'module');
        self::assertSame('demo_module', $manifest->id());
        self::assertFalse($manifest->isCore());
        $manifest->assertCompatible('2.0.0-dev');

        foreach ([
            ['id' => 'Bad-Id'],
            ['id' => 'demo', 'namespace' => 'NoTrailingSlash'],
            ['id' => 'demo', 'version' => 'not-semver'],
            ['id' => 'demo', 'src' => '../escape'],
            ['id' => 'demo', 'storage' => ['tables' => []]],
        ] as $invalid) {
            try {
                ModuleManifest::fromArray($invalid + [
                    'type' => 'module',
                    'namespace' => 'Demo\\Module\\',
                    'version' => '1.0.0',
                ], '/tmp/demo', 'module');
                self::fail('Ожидалась ошибка манифеста');
            } catch (InvalidArgumentException $error) {
                self::assertNotEmpty($error->getMessage());
            }
        }
    }

    public function testInstallLifecycleAndMigration(): void
    {
        $database = $this->databaseFile();
        try {
            $app = $this->boot($database);
            $installer = $app->container()->make(ModuleInstaller::class);

            $result = $installer->install('example_sdk');
            self::assertSame('example_sdk', $result['id']);
            self::assertNotEmpty($result['migrations']);
            self::assertTrue($app->database()->schema()->hasTable('module_example_sdk_notes'));

            $row = $app->database()->connection()->table('module_registry')->where('module_id', 'example_sdk')->first();
            self::assertNotNull($row);
            self::assertSame(1, (int)$row->enabled);

            $installer->disable('example_sdk');
            self::assertSame(0, (int)$app->database()->connection()->table('module_registry')->where('module_id', 'example_sdk')->value('enabled'));
            $installer->enable('example_sdk');
            self::assertSame(1, (int)$app->database()->connection()->table('module_registry')->where('module_id', 'example_sdk')->value('enabled'));

            try {
                $installer->disable('shoutbox');
                self::fail('Core-модуль нельзя выключить');
            } catch (InvalidArgumentException $error) {
                self::assertNotEmpty($error->getMessage());
            }

            $installer->remove('example_sdk');
            self::assertNull($app->database()->connection()->table('module_registry')->where('module_id', 'example_sdk')->whereNull('deleted_at')->first());
        } finally {
            @unlink($database);
        }
    }

    public function testInstalledModuleRoutesAreRegistered(): void
    {
        $database = $this->databaseFile();
        try {
            $app = $this->boot($database);
            $app->container()->make(ModuleInstaller::class)->install('example_sdk');

            $session = new ArraySession();
            $session->login([
                'id' => 1,
                'username' => 'admin',
                'name' => 'Администратор',
                'is_admin' => true,
                'stamp' => hash('sha256', ''),
            ]);
            $next = new Application(dirname(__DIR__, 2), [
                'debug' => true,
                'log' => 'php://stderr',
                'session' => $session,
                'database' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => ''],
            ]);
            $next->boot();

            $response = $next->handle(Request::create('/module/example_sdk', 'GET'));
            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString('SDK Example', (string)$response->getContent());
        } finally {
            @unlink($database);
        }
    }

    private function boot(string $database): Application
    {
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => new ArraySession(),
            'database' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => ''],
        ]);
        $app->boot();
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
        if (!$schema->hasTable('users')) {
            $schema->create('users', static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username', 190);
                $table->string('name', 190);
                $table->boolean('is_admin')->default(false);
                $table->string('password', 255)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
            $app->database()->connection()->table('users')->insert([
                'id' => 1,
                'username' => 'admin',
                'name' => 'Администратор',
                'is_admin' => 1,
                'password' => '',
                'created_at' => '2026-10-26 12:00:00',
                'updated_at' => '2026-10-26 12:00:00',
            ]);
        }

        return $app;
    }

    private function databaseFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'mydomo_sdk_');
        if ($file === false) {
            self::fail('Не удалось создать временную БД');
        }
        @unlink($file);
        $database = $file . '.sqlite';
        touch($database);

        return $database;
    }
}
