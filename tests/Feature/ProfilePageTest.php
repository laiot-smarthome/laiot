<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class ProfilePageTest extends TestCase
{
    public function testNameAndPasswordCanBeChanged(): void
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
        $app->database()->connection()->table('users')->insert([
            'username' => 'admin', 'name' => 'Admin', 'password' => password_hash('secret123', PASSWORD_DEFAULT),
            'is_admin' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $page = $app->handle(Request::create('/admin/profile', 'GET'));
        self::assertSame(200, $page->getStatusCode());

        $save = $app->handle(Request::create('/admin/profile', 'POST', [
            '_token' => $session->csrfToken(),
            'name' => 'Главный админ',
            'password' => 'newsecret123',
        ]));
        self::assertSame(302, $save->getStatusCode());
        $row = $app->database()->connection()->table('users')->where('id', 1)->first();
        self::assertSame('Главный админ', $row->name);
        self::assertTrue(password_verify('newsecret123', $row->password));
        self::assertSame('Главный админ', $session->user()['name']);
    }
}
