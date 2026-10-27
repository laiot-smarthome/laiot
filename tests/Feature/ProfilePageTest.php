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
 * 2026-10-27 — новый пароль соответствует общему требованию спецсимвола.
 */
final class ProfilePageTest extends TestCase
{
    public function testNameAndPasswordCanBeChanged(): void
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
        $app->database()->connection()->table('users')->insert([
            'username' => 'admin', 'name' => 'Admin', 'password' => password_hash('secret123', PASSWORD_DEFAULT),
            'is_admin' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        SessionFixture::authenticate($app);

        $page = $app->handle(Request::create('/admin/profile', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        $oldStamp = $session->user()['stamp'];

        $save = $app->handle(Request::create('/admin/profile', 'POST', [
            '_token' => $session->csrfToken(),
            'name' => 'Главный админ',
            'password' => 'newsecret!123',
        ]));
        self::assertSame(302, $save->getStatusCode());
        $row = $app->database()->connection()->table('users')->where('id', 1)->first();
        self::assertSame('Главный админ', $row->name);
        self::assertTrue(password_verify('newsecret!123', $row->password));
        self::assertSame('Главный админ', $session->user()['name']);
        self::assertNotSame($oldStamp, $session->user()['stamp']);
        $next = $app->handle(Request::create('/admin/profile', 'GET'));
        self::assertSame(200, $next->getStatusCode());
    }
}
