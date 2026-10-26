<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Support\MaintenanceMode;
use PHPUnit\Framework\TestCase;

final class MaintenanceTest extends TestCase
{
    public function testMaintenanceBlocksAdminButKeepsHealth(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $session,
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        $mode = $app->container()->make(MaintenanceMode::class);

        try {
            $mode->enable();

            $admin = $app->handle(Request::create('/admin', 'GET'));
            self::assertSame(503, $admin->getStatusCode());

            $page = $app->handle(Request::create('/admin/maintenance', 'GET'));
            self::assertSame(200, $page->getStatusCode());

            $health = $app->handle(Request::create('/health', 'GET'));
            self::assertSame(200, $health->getStatusCode());
        } finally {
            $mode->disable();
        }
    }
}
