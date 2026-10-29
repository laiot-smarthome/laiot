<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use LaIOT\Services\Auth\ApiTokenRepository;
use PHPUnit\Framework\TestCase;

/**
 * 2026-10-29 — токен не переживает удаление владельца; права панели независимы.
 * 2026-10-27 — фикстуры ролей учитывают удалённые назначения и разрешения.
 */
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
            'cache' => ['driver' => 'array'],
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $this->app->boot();
        SessionFixture::authenticate($this->app);
        $this->app->database()->connection()->table('users')->insert([
            'id' => 7, 'username' => 'api-owner', 'name' => 'API owner',
            'password' => password_hash('fixture-password', PASSWORD_DEFAULT), 'is_admin' => 0,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
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
            $table->softDeletes();
        });
        $this->app->database()->schema()->create('permissions', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('title');
            $table->softDeletes();
        });
        $this->app->database()->schema()->create('role_permissions', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('role_id');
            $table->integer('permission_id');
            $table->softDeletes();
        });
        $this->app->database()->schema()->create('user_roles', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('role_id');
            $table->softDeletes();
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

    public function testDeletedOwnerCannotAuthenticateAndUsageIsNotUpdated(): void
    {
        $token = $this->tokens->issue(7, 'test');
        $connection = $this->app->database()->connection();
        $connection->table('users')->where('id', 7)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $response = $this->app->handle(Request::create('/api/v1/me', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]));
        self::assertSame(401, $response->getStatusCode());
        self::assertNull($connection->table('api_tokens')->value('last_used_at'));

        $connection->table('users')->where('id', 7)->delete();
        self::assertNull($this->tokens->userId($token));
    }

    public function testTokenCannotBeIssuedForMissingOwner(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->tokens->issue(999, 'missing');
    }

    public function testExpiredTokenIsRejected(): void
    {
        $token = $this->tokens->issue(7, 'expired');
        $this->app->database()->connection()->table('api_tokens')->update(['expires_at' => '2000-01-01 00:00:00']);
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
