<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2026-10-26 — сброс лимита проверяется полным циклом входа.
 */
final class LoginTest extends TestCase
{
    private Application $app;
    private ArraySession $session;

    protected function setUp(): void
    {
        $this->session = new ArraySession();
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'view_cache' => sys_get_temp_dir() . '/mydomo-views-' . bin2hex(random_bytes(4)),
            'log' => 'php://stderr',
            'session' => $this->session,
            'cache' => ['driver' => 'array'],
            'database' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
        $this->app->boot();

        $this->app->database()->schema()->create('users', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username');
            $table->string('name')->default('');
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        $this->app->database()->connection()->table('users')->insert([
            'username' => 'admin',
            'name' => 'Admin',
            'password' => password_hash('secret', PASSWORD_DEFAULT),
            'is_admin' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function testLoginPageIsAvailable(): void
    {
        $response = $this->app->handle(Request::create('/login', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Вход в панель управления', (string)$response->getContent());
        self::assertStringContainsString('md-auth', (string)$response->getContent());
        self::assertStringContainsString('_token', (string)$response->getContent());
    }

    public function testValidCredentialsCreateSession(): void
    {
        $response = $this->attempt('secret');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin', $response->headers->get('Location'));
        self::assertSame('admin', $this->session->user()['username'] ?? null);
    }

    public function testInvalidCredentialsAreRejected(): void
    {
        $response = $this->attempt('wrong');

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('Неверный логин или пароль', (string)$response->getContent());
        self::assertNull($this->session->user());
    }

    public function testLoginRejectsMissingCsrf(): void
    {
        $response = $this->app->handle(Request::create('/login', 'POST', [
            'username' => 'admin',
            'password' => 'secret',
        ]));

        self::assertSame(403, $response->getStatusCode());
        self::assertNull($this->session->user());
    }

    public function testLogoutClearsSession(): void
    {
        $this->session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin']);
        SessionFixture::authenticate($this->app);

        $response = $this->app->handle(Request::create('/logout', 'POST', [
            '_token' => $this->session->csrfToken(),
        ]));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->headers->get('Location'));
        self::assertNull($this->session->user());
    }

    public function testSuccessfulLoginClearsFailedAttempts(): void
    {
        for ($i = 0; $i < 4; $i++) {
            self::assertSame(422, $this->attempt('wrong')->getStatusCode());
        }
        self::assertSame(302, $this->attempt('secret')->getStatusCode());
        $this->session->logout();
        self::assertSame(422, $this->attempt('wrong')->getStatusCode());
    }

    private function attempt(string $password): Response
    {
        return $this->app->handle(Request::create('/login', 'POST', [
            '_token' => $this->session->csrfToken(),
            'username' => 'admin',
            'password' => $password,
        ]));
    }
}
