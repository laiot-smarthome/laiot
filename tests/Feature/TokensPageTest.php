<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 */
final class TokensPageTest extends TestCase
{
    public function testTokenCanBeIssuedAndRevokedFromAdmin(): void
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
        $app->database()->schema()->create('api_tokens', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        $app->database()->connection()->table('users')->insert([
            'username' => 'admin', 'name' => 'Admin', 'password' => password_hash('secret123', PASSWORD_DEFAULT),
            'is_admin' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        SessionFixture::authenticate($app);

        $page = $app->handle(Request::create('/admin/tokens', 'GET'));
        self::assertSame(200, $page->getStatusCode());

        $issue = $app->handle(Request::create('/admin/tokens', 'POST', [
            '_token' => $session->csrfToken(),
            'user_id' => 1,
            'name' => 'monitoring',
            'days' => '0',
        ]));
        self::assertSame(200, $issue->getStatusCode());
        self::assertStringContainsString('Токен выпущен', (string)$issue->getContent());
        $row = $app->database()->connection()->table('api_tokens')->first();
        self::assertNotNull($row);

        $revoke = $app->handle(Request::create('/admin/tokens/' . $row->id . '/revoke', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $revoke->getStatusCode());
        self::assertNotNull($app->database()->connection()->table('api_tokens')->where('id', $row->id)->value('revoked_at'));
    }
}
