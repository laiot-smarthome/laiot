<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Properties\ValueRepository;
use PHPUnit\Framework\TestCase;

/**
 * История значений живёт в карточке объекта, а не отдельным разделом.
 *
 * 2026-10-27 — страница /history заменена вкладкой объекта; проверяется
 * запись истории и её отображение у конкретного объекта.
 */
final class CoreHistoryTest extends TestCase
{
    public function testValueChangeIsRecordedAndListedOnObjectCard(): void
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
        foreach ([
            'classes' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title');
                $table->integer('parent_id')->default(0);
                $table->timestamps();
                $table->softDeletes();
            },
            'objects' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title');
                $table->integer('class_id')->default(0);
                $table->integer('location_id')->default(0);
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
            },
            'properties' => static function (Blueprint $table): void {
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
            },
            'property_values' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('object_id');
                $table->integer('property_id');
                $table->text('value')->nullable();
                $table->timestamps();
                $table->softDeletes();
            },
            'property_history' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('object_id');
                $table->integer('property_id');
                $table->text('value')->nullable();
                $table->timestamps();
                $table->softDeletes();
            },
            'methods' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('class_id')->default(0);
                $table->integer('object_id')->default(0);
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('code')->nullable();
                $table->timestamps();
                $table->softDeletes();
            },
            'locations' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title');
                $table->integer('priority')->default(0);
                $table->timestamps();
                $table->softDeletes();
            },
        ] as $name => $definition) {
            $app->database()->schema()->create($name, $definition);
        }
        $now = date('Y-m-d H:i:s');
        $app->database()->connection()->table('classes')->insert([
            'id' => 1, 'title' => 'Датчик', 'parent_id' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $app->database()->connection()->table('objects')->insert(['title' => 'Кухня', 'class_id' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $app->database()->connection()->table('properties')->insert(['class_id' => 1, 'title' => 'temperature', 'created_at' => $now, 'updated_at' => $now]);

        $app->container()->make(ValueRepository::class)->set(1, 1, '23.5');

        SessionFixture::authenticate($app);

        $page = $app->handle(Request::create('/objects/1/edit', 'GET', ['tab' => 'history']));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('23.5', (string)$page->getContent());
        self::assertSame('23.5', getHistory('Кухня.temperature')[0]['value'] ?? null);
    }
}
