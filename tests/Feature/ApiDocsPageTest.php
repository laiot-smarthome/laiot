<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class ApiDocsPageTest extends TestCase
{
    public function testApiPageListsRoutes(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();

        $response = $app->handle(Request::create('/admin/api', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('/api/v1/health', (string)$response->getContent());
        self::assertStringContainsString('/api/v1/me', (string)$response->getContent());
    }
}
