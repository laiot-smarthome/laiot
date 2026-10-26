<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Auth\ApiTokenRepository;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class ApiTokenTest extends TestCase
{
    private Application $app;
    private ApiTokenRepository $tokens;

    protected function setUp(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $this->app->boot();
        $this->app->database()->schema()->create('api_tokens', static function (Blueprint $table): void {
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
        $this->app->database()->schema()->create('roles', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('title');
        });
        $this->app->database()->schema()->create('permissions', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('title');
        });
        $this->app->database()->schema()->create('role_permissions', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('role_id');
            $table->integer('permission_id');
        });
        $this->app->database()->schema()->create('user_roles', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('role_id');
        });
        $this->app->database()->connection()->table('roles')->insert(['id' => 1, 'name' => 'admin', 'title' => 'Администратор']);
        $this->app->database()->connection()->table('permissions')->insert(['id' => 1, 'name' => '*', 'title' => 'Все']);
        $this->app->database()->connection()->table('role_permissions')->insert(['role_id' => 1, 'permission_id' => 1]);
        $this->app->database()->connection()->table('user_roles')->insert(['user_id' => 7, 'role_id' => 1]);

        $this->tokens = $this->app->container()->make(ApiTokenRepository::class);
    }

    public function testIssueResolveAndRevoke(): void
    {
        $token = $this->tokens->issue(7, 'test');
        self::assertSame(7, $this->tokens->userId($token));

        $id = $this->app->database()->connection()->table('api_tokens')->value('id');
        self::assertTrue($this->tokens->revoke((int)$id));
        self::assertNull($this->tokens->userId($token));
    }

    public function testApiMeRequiresValidBearer(): void
    {
        $token = $this->tokens->issue(7, 'test');

        $ok = $this->app->handle(Request::create('/api/v1/me', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]));
        self::assertSame(200, $ok->getStatusCode());
        $payload = json_decode((string)$ok->getContent(), true);
        self::assertSame(7, $payload['user_id']);
        self::assertSame(['admin'], $payload['roles']);
        self::assertSame(['*'], $payload['permissions']);

        $missing = $this->app->handle(Request::create('/api/v1/me', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]));
        self::assertSame(401, $missing->getStatusCode());
    }
}
