<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Install\InstallService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2026-10-28 — до установки страницы уводятся на /install, API получает 503.
 */
final class InstallRedirectTest extends TestCase
{
    private Application $app;
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mydomo-install-redirect-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/var', 0775, true);
        $this->app = new Application(dirname(__DIR__, 2), [
            'install_guard' => true,
            'debug' => true,
            'log' => 'php://stderr',
            'session' => new ArraySession(),
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $this->app->boot();
        $this->app->container()->instance(InstallService::class, new InstallService(
            $this->app,
            $this->root,
            fn (array $config) => $this->app->database(),
            fn (array $config) => $this->app,
            dirname(__DIR__, 2) . '/database/migrations'
        ));
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testBrowserPagesRedirectToInstall(): void
    {
        foreach (['/', '/login', '/admin', '/classes'] as $path) {
            $response = $this->get($path);
            self::assertSame(302, $response->getStatusCode(), $path);
            self::assertSame('/install', $response->headers->get('Location'), $path);
        }
    }

    public function testInstallAndHealthStayReachable(): void
    {
        self::assertSame(200, $this->get('/install')->getStatusCode());
        self::assertSame(200, $this->get('/health')->getStatusCode());
    }

    public function testPostBeforeInstallRedirects(): void
    {
        $response = $this->app->handle(Request::create('/login', 'POST', [
            'username' => 'admin',
            'password' => 'secret',
        ]));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/install', $response->headers->get('Location'));
    }

    public function testApiGetsJsonInsteadOfRedirect(): void
    {
        $response = $this->app->handle(Request::create('/api/v1/me', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]));

        self::assertSame(503, $response->getStatusCode());
        $payload = json_decode((string)$response->getContent(), true);
        self::assertSame('not_installed', $payload['error']['code'] ?? null);
    }

    public function testInstalledSystemKeepsLoginPage(): void
    {
        file_put_contents($this->root . '/var/installed.lock', '{}');

        self::assertSame(200, $this->get('/login')->getStatusCode());
    }

    public function testAdminInDatabaseRestoresMissingLock(): void
    {
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
            'name' => 'Администратор',
            'password' => password_hash('secret', PASSWORD_DEFAULT),
            'is_admin' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        self::assertSame(200, $this->get('/login')->getStatusCode());
        self::assertFileExists($this->root . '/var/installed.lock');
    }

    private function get(string $path): Response
    {
        return $this->app->handle(Request::create($path, 'GET'));
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                unlink($path);
            }

            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->remove($path . '/' . $item);
        }
        rmdir($path);
    }
}
