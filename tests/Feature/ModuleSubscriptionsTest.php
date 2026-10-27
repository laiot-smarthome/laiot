<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Modules\ModuleInstaller;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Modules\ModuleSubscriptions;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertySubscriptionRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Подписки модуля на свойства: SDK-фасад от имени текущего модуля.
 *
 * Проверяется, что модуль не может подписаться за другого, что свойство
 * проверяется по действующим свойствам объекта и что повторная подписка
 * идемпотентна. Сама доставка проверяется в PropertyDeliveryTest.
 *
 * 2026-10-27 — тест создан вместе с ModuleSubscriptions.
 */
final class ModuleSubscriptionsTest extends TestCase
{
    public function testModuleAttachesAndDetachesApplicableProperties(): void
    {
        $app = $this->boot();
        $subscriptions = $this->subscriptions($app);

        $subscriptions->attach(1, 1);
        $subscriptions->attach(1, 4);
        $subscriptions->attach(1, 1);

        self::assertTrue($subscriptions->isAttached(1, 1));
        self::assertTrue($subscriptions->isAttached(1, 4));
        self::assertSame(['example_sdk'], $app->container()
            ->make(PropertySubscriptionRepository::class)
            ->modulesFor(1, 1));

        $attached = $subscriptions->attached();
        self::assertCount(2, $attached);
        self::assertSame('Кухня', $attached[0]['object_title']);
        self::assertSame('temperature', $attached[0]['property_title']);
        self::assertSame('local', $attached[1]['property_title']);

        self::assertTrue($subscriptions->detach(1, 1));
        self::assertFalse($subscriptions->isAttached(1, 1));
        self::assertFalse($subscriptions->detach(1, 1));
        self::assertCount(1, $subscriptions->attached());
    }

    public function testForeignPropertyAndMissingObjectAreRejected(): void
    {
        $app = $this->boot();
        $subscriptions = $this->subscriptions($app);

        foreach ([
            [1, 3, 'не действует'],
            [99, 1, 'Объект не найден'],
            [1, 99, 'Свойство не найдено'],
        ] as [$objectId, $propertyId, $message]) {
            try {
                $subscriptions->attach($objectId, $propertyId);
                self::fail('Ожидалась ошибка подписки');
            } catch (InvalidArgumentException $error) {
                self::assertStringContainsString($message, $error->getMessage());
            }
        }

        self::assertSame(0, $app->database()->connection()->table('property_subscriptions')->count());
    }

    public function testModuleWithoutSubscriberCannotSubscribe(): void
    {
        $app = $this->boot();
        $subscriptions = new ModuleSubscriptions(
            'ghost',
            false,
            $app->container()->make(PropertySubscriptionRepository::class),
            $app->container()->make(PropertyRepository::class),
            $app->container()->make(ObjectRepository::class)
        );

        try {
            $subscriptions->attach(1, 1);
            self::fail('Модуль без property_subscriber не должен подписываться');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('property_subscriber', $error->getMessage());
        }
    }

    private function subscriptions(Application $app): ModuleSubscriptions
    {
        return $app->container()->make(ModuleManager::class)
            ->context('example_sdk')
            ->subscriptions();
    }

    private function boot(): Application
    {
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => new ArraySession(),
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        $this->createSchema($app);
        $this->seed($app);
        $app->container()->make(ModuleInstaller::class)->install('example_sdk');

        return $app;
    }

    private function createSchema(Application $app): void
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

        $schema->create('classes', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->integer('parent_id')->default(0);
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
        $schema->create('property_subscriptions', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('object_id');
            $table->integer('property_id');
            $table->string('module', 64);
            $table->timestamps();
            $table->unique(['object_id', 'property_id', 'module']);
        });
    }

    private function seed(Application $app): void
    {
        $connection = $app->database()->connection();
        $now = date('Y-m-d H:i:s');

        $connection->table('classes')->insert([
            ['id' => 1, 'title' => 'Датчик', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'title' => 'Другое', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('objects')->insert([
            ['id' => 1, 'title' => 'Кухня', 'class_id' => 1, 'location_id' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'title' => 'Лампа', 'class_id' => 1, 'location_id' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('properties')->insert([
            ['id' => 1, 'class_id' => 1, 'object_id' => 0, 'title' => 'temperature', 'data_type' => 'float', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'class_id' => 1, 'object_id' => 0, 'title' => 'state', 'data_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'class_id' => 2, 'object_id' => 0, 'title' => 'other', 'data_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'class_id' => 1, 'object_id' => 1, 'title' => 'local', 'data_type' => 'string', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
