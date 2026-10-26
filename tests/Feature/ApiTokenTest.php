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
            $table->increments('ID');
            $table->integer('USER_ID');
            $table->string('NAME');
            $table->string('TOKEN_HASH', 64)->unique();
            $table->dateTime('CREATED_AT');
            $table->dateTime('EXPIRES_AT')->nullable();
            $table->dateTime('LAST_USED_AT')->nullable();
            $table->dateTime('REVOKED_AT')->nullable();
        });
        $this->tokens = $this->app->container()->make(ApiTokenRepository::class);
    }

    public function testIssueResolveAndRevoke(): void
    {
        $token = $this->tokens->issue(7, 'test');
        self::assertSame(7, $this->tokens->userId($token));

        $id = $this->app->database()->connection()->table('api_tokens')->value('ID');
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
        self::assertSame(7, json_decode((string)$ok->getContent(), true)['user_id']);

        $missing = $this->app->handle(Request::create('/api/v1/me', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]));
        self::assertSame(401, $missing->getStatusCode());
    }
}
