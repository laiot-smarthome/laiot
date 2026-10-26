<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class WrappersTest extends TestCase
{
    public function testGgSgAndGetObject(): void
    {
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => new ArraySession(),
            'cache' => ['driver' => 'array'],
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
            'methods' => static function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('class_id');
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('code')->nullable();
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
        $app->database()->connection()->table('objects')->insert([
            'title' => 'Кухня', 'class_id' => 1, 'location_id' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $app->database()->connection()->table('methods')->insert([
            'class_id' => 1, 'title' => 'double', 'code' => 'return ((int)($params["x"] ?? 0)) * 2;',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        self::assertSame(10, callMethod('Кухня', 'double', ['x' => 5]));
        self::assertTrue(sg('Кухня.temperature', '23.5'));
        self::assertSame('23.5', gg('Кухня.temperature'));
        self::assertSame('Кухня', getObject('Кухня')['title'] ?? null);
        self::assertFalse(sg('Нет.нет', 1));
    }
}
