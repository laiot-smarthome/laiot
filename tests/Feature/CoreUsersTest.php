<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class CoreUsersTest extends TestCase
{
    public function testUserCanBeCreatedEditedAndSoftDeleted(): void
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
        $app->database()->schema()->create('users', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username')->unique();
            $table->string('name')->default('');
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        $app->database()->schema()->create('roles', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('title');
            $table->dateTime('deleted_at')->nullable();
        });
        $app->database()->schema()->create('user_roles', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('role_id');
            $table->timestamps();
            $table->softDeletes();
        });
        $app->database()->connection()->table('roles')->insert(['id' => 1, 'name' => 'user', 'title' => 'Пользователь']);

        $page = $app->handle(Request::create('/module/users', 'GET'));
        self::assertSame(200, $page->getStatusCode());

        $create = $app->handle(Request::create('/module/users/create', 'POST', [
            '_token' => $session->csrfToken(),
            'username' => 'operator',
            'name' => 'Оператор',
            'password' => 'secret123',
            'is_admin' => '1',
            'role_ids' => [1],
        ]));
        self::assertSame(302, $create->getStatusCode());
        $row = $app->database()->connection()->table('users')->where('username', 'operator')->first();
        self::assertNotNull($row);
        self::assertSame(1, $app->database()->connection()->table('user_roles')->where('user_id', $row->id)->count());

        $edit = $app->handle(Request::create('/module/users/' . $row->id . '/edit', 'POST', [
            '_token' => $session->csrfToken(),
            'username' => 'operator',
            'name' => 'Оператор 2',
            'password' => '',
            'is_admin' => '0',
        ]));
        self::assertSame(302, $edit->getStatusCode());
        self::assertSame('Оператор 2', $app->database()->connection()->table('users')->where('id', $row->id)->value('name'));

        $delete = $app->handle(Request::create('/module/users/' . $row->id . '/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNull($app->database()->connection()->table('users')->where('id', $row->id)->whereNull('deleted_at')->first());
    }
}
