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
 */
final class CorePropertiesTest extends TestCase
{
    public function testPropertyCanBeCreatedAndDeleted(): void
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
        $app->database()->schema()->create('classes', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->integer('parent_id')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        $app->database()->schema()->create('properties', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('class_id')->default(0);
            $table->integer('object_id')->default(0);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('data_type')->default('string');
            $table->boolean('keep_history')->default(false);
            $table->string('on_change')->default('');
            $table->timestamps();
            $table->softDeletes();
        });
        $app->database()->connection()->table('classes')->insert([
            'title' => 'Датчик',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        SessionFixture::authenticate($app);

        $create = $app->handle(Request::create('/properties/create', 'POST', [
            '_token' => $session->csrfToken(),
            'class_id' => 1,
            'title' => 'temperature',
            'data_type' => 'float',
        ]));
        self::assertSame(302, $create->getStatusCode());
        self::assertSame('temperature', $app->database()->connection()->table('properties')->value('title'));

        $row = $app->database()->connection()->table('properties')->first();
        $delete = $app->handle(Request::create('/properties/' . $row->id . '/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNull($app->database()->connection()->table('properties')->whereNull('deleted_at')->first());
    }
}
