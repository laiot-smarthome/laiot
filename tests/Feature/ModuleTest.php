<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class ModuleTest extends TestCase
{
    private Application $app;
    private ArraySession $session;

    protected function setUp(): void
    {
        $this->session = new ArraySession();
        $this->session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin']);
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'view_cache' => sys_get_temp_dir() . '/mydomo-views-' . bin2hex(random_bytes(4)),
            'log' => 'php://stderr',
            'session' => $this->session,
        ]);
        $this->app->boot();
    }

    public function testManifestIsDiscovered(): void
    {
        self::assertArrayHasKey('welcome', $this->app->modules()->all());
        self::assertNotNull($this->app->router()->getRoutes()->getByName('welcome.index'));
    }

    public function testModulePageUsesNamespacedBladeView(): void
    {
        $response = $this->app->handle(Request::create('/module/welcome', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Welcome module', (string)$response->getContent());
        self::assertStringContainsString('module.json', (string)$response->getContent());
    }

    public function testModuleRouteParameterReachesController(): void
    {
        $response = $this->app->handle(Request::create('/module/welcome/hello/MyDoMo', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Привет, MyDoMo!', (string)$response->getContent());
    }

    public function testModuleRoutesRequireAuthentication(): void
    {
        $this->session->logout();

        $response = $this->app->handle(Request::create('/module/welcome', 'GET'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->headers->get('Location'));
    }
}
