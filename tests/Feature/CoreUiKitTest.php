<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 * 2026-10-27 — проверяются семантика типографики и навигационной цепочки.
 * 2026-10-27 — проверяются экранирование JSON и безопасные ссылки дерева.
 * 2026-10-27 — проверяются цепочка с источником и изменением значения.
 * 2026-10-27 — проверяются новые примеры форм и таблицы.
 * 2026-10-28 — проверяются ссылки без подчёркивания и внешние адреса.
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
        self::assertStringContainsString('md-kit-links', $html);
        self::assertStringContainsString('md-link md-link--primary', $html);
        self::assertStringContainsString('md-link md-link--hint', $html);
        self::assertStringContainsString('md-link md-link--action', $html);
        self::assertStringContainsString('hx-get="increment"', $html);
        self::assertStringContainsString('yoyo:name="counter"', $html);
        self::assertStringContainsString('/assets/vendor/yoyo/yoyo.js', $html);
        self::assertStringContainsString('kit-dialog', $html);
        self::assertStringContainsString('$store.mdModal', $html);
        self::assertStringContainsString('x-data="mdSearchSelect"', $html);
        self::assertStringContainsString('name="demo_multi[]"', $html);
        self::assertStringContainsString('mdDataTable(', $html);
        self::assertStringContainsString('aria-describedby="md-input-', $html);
        self::assertStringContainsString('Выбор карточками', $html);
        self::assertStringContainsString('aria-controls="left_block"', $html);
        self::assertStringContainsString('yoyo:name="folder-picker"', $html);
        self::assertStringContainsString('Light.status', $html);
        self::assertStringContainsString('Источник: Сценарий', $html);
        self::assertStringContainsString('Было: <strong>0</strong>', $html);
        self::assertStringContainsString('Стало: <strong>1</strong>', $html);
        self::assertStringContainsString('#4', $html);
        $entityText = $app->container()->make(Factory::class)->make('components.md.entity-text', [
            'text' => '<script>alert(1)</script> [[property:Kitchen.Temperature|Температура|javascript:alert]]',
        ])->render();
        self::assertStringNotContainsString('<script>', $entityText);
        self::assertStringNotContainsString('href="javascript:', $entityText);
        self::assertStringContainsString('Температура', $entityText);

        $tree = $app->container()->make(Factory::class)->make('components.md.object-tree', [
            'items' => [['label' => '<script>alert(1)</script>', 'href' => 'javascript:alert(1)']],
        ])->render();
        self::assertStringNotContainsString('<script>', $tree);
        self::assertStringNotContainsString('href="javascript:', $tree);

        $json = $app->container()->make(Factory::class)->make('components.md.json-view', [
            'data' => ['message' => '<script>alert(1)</script>'],
        ])->render();
        self::assertStringContainsString('data-language="json"', $json);
        self::assertStringNotContainsString('<script>', $json);

        $breadcrumbs = $app->container()->make(Factory::class)->make('components.md.breadcrumbs', [
            'items' => [['label' => 'Панель', 'href' => '/admin'], ['label' => 'Текущая', 'href' => '/current']],
        ])->render();
        self::assertStringContainsString('href="/admin"', $breadcrumbs);
        self::assertStringContainsString('aria-current="page"', $breadcrumbs);
        self::assertStringNotContainsString('href="/current"', $breadcrumbs);

        $unsafeBreadcrumb = $app->container()->make(Factory::class)->make('components.md.breadcrumbs', [
            'items' => [['label' => 'Опасная ссылка', 'href' => 'javascript:alert(1)'], ['label' => 'Текущая']],
        ])->render();
        self::assertStringNotContainsString('href="javascript:', $unsafeBreadcrumb);

        $externalLink = $app->container()->make(Factory::class)->make('components.md.link', [
            'href' => 'https://example.com/docs',
            'variant' => 'muted',
            'slot' => 'Документация',
        ])->render();
        self::assertStringContainsString('target="_blank"', $externalLink);
        self::assertStringContainsString('rel="noopener noreferrer"', $externalLink);
        self::assertStringContainsString('ti-external-link', $externalLink);
        self::assertStringContainsString('md-link--muted', $externalLink);

        $unsafeLink = $app->container()->make(Factory::class)->make('components.md.link', [
            'href' => 'javascript:alert(1)',
            'slot' => 'Опасная ссылка',
        ])->render();
        self::assertStringNotContainsString('href="javascript:', $unsafeLink);
        self::assertStringContainsString('aria-disabled="true"', $unsafeLink);
    }
}
