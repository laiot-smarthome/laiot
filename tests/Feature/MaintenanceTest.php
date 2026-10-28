<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Kernel;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Support\MaintenanceMode;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * 2026-10-26 — управление обслуживанием проверяется с отдельным временным флагом.
 */
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
        SessionFixture::authenticate($app);

        $root = sys_get_temp_dir() . '/mydomo-maintenance-' . bin2hex(random_bytes(6));
        $mode = new MaintenanceMode($root);
        $app->container()->instance(MaintenanceMode::class, $mode);
        $kernel = new Kernel(
            $app->router(),
            new NullLogger(),
            maintenance: $mode,
            view: $app->container()->make(ViewFactory::class)
        );

        try {
            $mode->enable();

            $admin = $kernel->handle(Request::create('/admin', 'GET'));
            self::assertSame(503, $admin->getStatusCode());
            self::assertStringContainsString('Идут технические работы', (string)$admin->getContent());
            self::assertSame('60', $admin->headers->get('Retry-After'));

            $page = $kernel->handle(Request::create('/admin/maintenance', 'GET'));
            self::assertSame(200, $page->getStatusCode());

            $health = $kernel->handle(Request::create('/health', 'GET'));
            self::assertSame(200, $health->getStatusCode());

            $withoutCsrf = $kernel->handle(Request::create('/admin/maintenance/disable', 'POST'));
            self::assertSame(403, $withoutCsrf->getStatusCode());
            self::assertTrue($mode->enabled());

            $stored = $session->user();
            self::assertNotNull($stored);
            $logout = $kernel->handle(Request::create('/logout', 'POST', [
                '_token' => $session->csrfToken(),
            ]));
            self::assertSame(302, $logout->getStatusCode());
            self::assertNull($session->user());
            $guest = $kernel->handle(Request::create('/admin/maintenance/disable', 'POST'));
            self::assertSame(401, $guest->getStatusCode());
            self::assertTrue($mode->enabled());

            $session->login($stored);
            $disable = $kernel->handle(Request::create('/admin/maintenance/disable', 'POST', [
                '_token' => $session->csrfToken(),
            ]));
            self::assertSame(302, $disable->getStatusCode());
            self::assertFalse($mode->enabled());
        } finally {
            $mode->disable();
            if (is_dir($root . '/var')) {
                rmdir($root . '/var');
            }
            if (is_dir($root)) {
                rmdir($root);
            }
        }
    }
}
