<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 * 2026-10-27 — проверяются новые примеры форм и таблицы.
 */
final class CoreUiKitTest extends TestCase
{
    public function testUiKitPageRendersComponents(): void
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

        $response = $app->handle(Request::create('/ui-kit', 'GET'));
        $html = (string)$response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('UI Kit', $html);
        self::assertStringContainsString('md-kpi', $html);
        self::assertStringContainsString('hx-get="increment"', $html);
        self::assertStringContainsString('yoyo:name="counter"', $html);
        self::assertStringContainsString('/assets/vendor/yoyo/yoyo.js', $html);
        self::assertStringContainsString('blade-demo-modal', $html);
        self::assertStringContainsString('$store.mdModal', $html);
        self::assertStringContainsString('x-data="mdSearchSelect"', $html);
        self::assertStringContainsString('name="demo_multi[]"', $html);
        self::assertStringContainsString('mdDataTable(', $html);
        self::assertStringContainsString('aria-describedby="md-input-', $html);
        self::assertStringContainsString('Выбор карточками', $html);
    }
}
