<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 */
final class DashboardPageTest extends TestCase
{
    public function testAdminDashboardRendersOnBlade(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'cache' => ['driver' => 'array'],
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();

        SessionFixture::authenticate($app);

        $response = $app->handle(Request::create('/admin', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('md-admin-shell', $html);
        self::assertStringContainsString('Обзор', $html);
        self::assertStringContainsString('/notifications', $html);
        self::assertStringContainsString('/jobs', $html);
    }
}
