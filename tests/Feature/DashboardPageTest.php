<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class DashboardPageTest extends TestCase
{
    public function testAdminDashboardRendersOnBlade(): void
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

        $response = $app->handle(Request::create('/admin', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('md-admin-shell', $html);
        self::assertStringContainsString('Обзор', $html);
        self::assertStringContainsString('/module/shoutbox', $html);
        self::assertStringContainsString('/module/jobs', $html);
    }
}
