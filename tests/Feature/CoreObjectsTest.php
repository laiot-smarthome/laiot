<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class CoreObjectsTest extends TestCase
{
    public function testObjectAndPropertyValueCanBeSaved(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        foreach ([
            'classes' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title');
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
            'properties' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('class_id');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('data_type')->default('string');
                $table->boolean('keep_history')->default(false);
                $table->string('on_change')->default('');
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
        ] as $name => $definition) {
            $app->database()->schema()->create($name, $definition);
        }
        $now = date('Y-m-d H:i:s');
        $app->database()->connection()->table('classes')->insert(['title' => 'Датчик', 'created_at' => $now, 'updated_at' => $now]);
        $app->database()->connection()->table('properties')->insert([
            'class_id' => 1, 'title' => 'temperature', 'data_type' => 'float', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $page = $app->handle(Request::create('/module/objects', 'GET'));
        self::assertSame(200, $page->getStatusCode());

        $create = $app->handle(Request::create('/module/objects/create', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Кухня',
            'class_id' => 1,
            'location_id' => 0,
        ]));
        self::assertSame(302, $create->getStatusCode());
        $object = $app->database()->connection()->table('objects')->first();
        self::assertNotNull($object);

        $save = $app->handle(Request::create('/module/objects/' . $object->id . '/edit', 'POST', [
            '_token' => $session->csrfToken(),
            'title' => 'Кухня',
            'class_id' => 1,
            'location_id' => 0,
            'values' => [1 => '23.5'],
        ]));
        self::assertSame(302, $save->getStatusCode());
        self::assertSame('23.5', $app->database()->connection()->table('property_values')->value('value'));
    }
}
