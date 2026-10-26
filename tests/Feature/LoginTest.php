<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Core\Users\Passwords;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

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
            $table->increments('ID');
            $table->string('USERNAME', 255);
            $table->string('NAME', 255)->default('');
            $table->string('PASSWORD', 255);
            $table->integer('IS_ADMIN')->default(0);
        });

        $this->app->database()->connection()->table('users')->insert([
            'USERNAME' => 'admin',
            'NAME' => 'Admin',
            'PASSWORD' => Passwords::hash('secret'),
            'IS_ADMIN' => 1,
        ]);
    }

    public function testLoginPageIsAvailable(): void
    {
        $response = $this->app->handle(Request::create('/login', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Вход в MyDoMo', (string)$response->getContent());
        self::assertStringContainsString('_token', (string)$response->getContent());
    }

    public function testValidCredentialsCreateSession(): void
    {
        $response = $this->app->handle(Request::create('/login', 'POST', [
            '_token' => $this->session->csrfToken(),
            'username' => 'admin',
            'password' => 'secret',
        ]));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin', $response->headers->get('Location'));
        self::assertSame('admin', $this->session->user()['username'] ?? null);
    }

    public function testInvalidCredentialsAreRejected(): void
    {
        $response = $this->app->handle(Request::create('/login', 'POST', [
            '_token' => $this->session->csrfToken(),
            'username' => 'admin',
            'password' => 'wrong',
        ]));

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

        $response = $this->app->handle(Request::create('/logout', 'POST', [
            '_token' => $this->session->csrfToken(),
        ]));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->headers->get('Location'));
        self::assertNull($this->session->user());
    }
}
