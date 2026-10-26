<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class CorePvaluesTest extends TestCase
{
    public function testValueCanBeEditedAndDeleted(): void
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
                $table->string('title');
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
        ] as $name => $definition) {
            $app->database()->schema()->create($name, $definition);
        }
        $now = date('Y-m-d H:i:s');
        $app->database()->connection()->table('objects')->insert(['title' => 'Кухня', 'created_at' => $now, 'updated_at' => $now]);
        $app->database()->connection()->table('properties')->insert(['class_id' => 1, 'title' => 'temperature', 'created_at' => $now, 'updated_at' => $now]);
        $app->database()->connection()->table('property_values')->insert([
            'object_id' => 1, 'property_id' => 1, 'value' => '20', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $page = $app->handle(Request::create('/module/pvalues', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('temperature', (string)$page->getContent());

        $update = $app->handle(Request::create('/module/pvalues/1', 'POST', [
            '_token' => $session->csrfToken(),
            'value' => '23.5',
        ]));
        self::assertSame(302, $update->getStatusCode());
        self::assertSame('23.5', $app->database()->connection()->table('property_values')->value('value'));

        $delete = $app->handle(Request::create('/module/pvalues/1/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNull($app->database()->connection()->table('property_values')->whereNull('deleted_at')->first());
    }
}
