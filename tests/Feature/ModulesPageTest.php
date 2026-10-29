<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * Страница SDK-модулей и управление их жизненным циклом.
 *
 * 2026-10-28 — результаты операций приходят один раз, без сообщений в URL.
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 * 2026-10-26 — добавлены действия enable/disable/install/remove и проверка CSRF.
 */
final class ModulesPageTest extends TestCase
{
    public function testCoreModulesAreListed(): void
    {
        $app = $this->boot();

        $response = $app->handle(Request::create('/admin/modules', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('Модули', $html);
        self::assertStringNotContainsString('Ядро и модули', $html);
        self::assertStringNotContainsString('То же самое из терминала', $html);
        self::assertStringNotContainsString('Shoutbox', $html);
    }

    public function testModuleActionsRequireCsrf(): void
    {
        $app = $this->boot();

        $denied = $app->handle(Request::create('/admin/modules/example_sdk/enable', 'POST'));

        self::assertSame(403, $denied->getStatusCode());
    }

    public function testUnknownModuleReportsErrorOnPage(): void
    {
        $app = $this->boot();
        $session = $app->session();

        $response = $app->handle(Request::create('/admin/modules/does_not_exist/enable', 'POST', [
            '_token' => $session->csrfToken(),
        ]));

        self::assertSame(302, $response->getStatusCode());
        $location = (string)$response->headers->get('Location');
        self::assertSame('/admin/modules', $location);

        $page = $app->handle(Request::create($location, 'GET'));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('не найден', (string)$page->getContent());
        self::assertStringContainsString('data-md-notify data-tone="danger"', (string)$page->getContent());
        $again = $app->handle(Request::create($location, 'GET'));
        self::assertStringNotContainsString('data-md-notify data-tone=', (string)$again->getContent());
    }

    private function boot(): Application
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

        return $app;
    }
}
