<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use PHPUnit\Framework\TestCase;

final class SpikeTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'view_cache' => sys_get_temp_dir() . '/mydomo-views-' . bin2hex(random_bytes(4)),
            'log' => 'php://stderr',
        ]);
        $this->app->boot();
    }

    public function testBladeAndContainerWork(): void
    {
        $response = $this->app->handle(Request::create('/spike', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('MyDoMo spike', (string)$response->getContent());
        self::assertStringContainsString('Blade работает.', (string)$response->getContent());
        self::assertSame('yes', $response->headers->get('X-Spike-Middleware'));
        self::assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function testJsonRoute(): void
    {
        $response = $this->app->handle(Request::create('/health', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('"status":"ok"', (string)$response->getContent());
        self::assertStringContainsString('"app":"MyDoMo"', (string)$response->getContent());
    }

    public function testRouteParameter(): void
    {
        $response = $this->app->handle(Request::create('/spike/42', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Spike item 42', (string)$response->getContent());
    }

    public function testNotFound(): void
    {
        $response = $this->app->handle(Request::create('/missing', 'GET'));

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('Not Found', (string)$response->getContent());
        self::assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function testMethodNotAllowed(): void
    {
        $response = $this->app->handle(Request::create('/spike/post-only', 'GET'));

        self::assertSame(405, $response->getStatusCode());
        self::assertStringContainsString('POST', (string)$response->headers->get('Allow'));
    }

    public function testNamedRouteIsRegistered(): void
    {
        $route = $this->app->router()->getRoutes()->getByName('spike');

        self::assertNotNull($route);
        self::assertSame('spike', $route->uri());
    }
}
