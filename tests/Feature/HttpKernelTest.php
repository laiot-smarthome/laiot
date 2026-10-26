<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class HttpKernelTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin']);
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'view_cache' => sys_get_temp_dir() . '/mydomo-views-' . bin2hex(random_bytes(4)),
            'log' => 'php://stderr',
            'session' => $session,
        ]);
        $this->app->boot();
    }

    public function testBladeAndContainerWork(): void
    {
        $response = $this->app->handle(Request::create('/yoyo-demo', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Yoyo + Blade', (string)$response->getContent());
        self::assertStringContainsString('increment', (string)$response->getContent());
        self::assertSame('yes', $response->headers->get('X-MyDoMo-Middleware'));
        self::assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function testHealthWithoutDatabase(): void
    {
        $response = $this->app->handle(Request::create('/health', 'GET'));

        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString('"status":"degraded"', (string)$response->getContent());
        self::assertStringContainsString('"database":"not_configured"', (string)$response->getContent());
    }

    public function testRootRedirectsToYoyoWhenLoggedIn(): void
    {
        $response = $this->app->handle(Request::create('/', 'GET'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/yoyo-demo', $response->headers->get('Location'));
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
        $response = $this->app->handle(Request::create('/yoyo', 'GET'));

        self::assertSame(405, $response->getStatusCode());
        self::assertStringContainsString('POST', (string)$response->headers->get('Allow'));
    }

    public function testNotFoundReturnsJsonForApiRequest(): void
    {
        $response = $this->app->handle(Request::create('/missing', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]));

        self::assertSame(404, $response->getStatusCode());
        $payload = json_decode((string)$response->getContent(), true);
        self::assertIsArray($payload);
        self::assertFalse($payload['ok']);
        self::assertSame('not_found', $payload['error']['code']);
        self::assertNotEmpty($payload['request_id']);
    }

    public function testInternalErrorDoesNotExposeTrace(): void
    {
        $this->app->router()->get('/boom', static function (): never {
            throw new \RuntimeException('boom detail');
        });

        $response = $this->app->handle(Request::create('/boom', 'GET'));

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('boom detail', (string)$response->getContent());
        self::assertStringNotContainsString('#0', (string)$response->getContent());
        self::assertStringNotContainsString(__FILE__, (string)$response->getContent());
    }

    public function testNamedRouteIsRegistered(): void
    {
        $route = $this->app->router()->getRoutes()->getByName('health');

        self::assertNotNull($route);
        self::assertSame('health', $route->uri());
    }
}
