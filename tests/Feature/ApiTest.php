<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin']);
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $session,
        ]);
        $this->app->boot();
    }

    public function testApiAbout(): void
    {
        $response = $this->app->handle(Request::create('/api/v1/about', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string)$response->getContent(), true);
        self::assertIsArray($payload);
        self::assertSame('LaIOT', $payload['app']);
        self::assertArrayHasKey('version', $payload);
        self::assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function testApiModulesReturnsList(): void
    {
        $response = $this->app->handle(Request::create('/api/v1/modules', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string)$response->getContent(), true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('modules', $payload);
    }

    public function testApiHealthReturnsJson(): void
    {
        $response = $this->app->handle(Request::create('/api/v1/health', 'GET'));

        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString('"status":"degraded"', (string)$response->getContent());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
    }
}
