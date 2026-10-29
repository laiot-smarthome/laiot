<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * Новый admin-shell не должен тянуть jQuery.
 *
 * Старый alpha-бандл admin.js остаётся для /panel/, но layout и вход нового
 * ядра обязаны грузить laiot.js.
 *
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 * 2026-10-27 — Tom Select входит в локальный бандл новых компонентов.
 */
final class AdminAssetsTest extends TestCase
{
    public function testLaIOTBundleHasNoJqueryOrAlphaRuntime(): void
    {
        $root = dirname(__DIR__, 2);
        $file = $root . '/public/assets/admin/laiot.js';
        self::assertFileExists($file);
        self::assertFileExists($root . '/public/assets/admin/laiot.css');
        self::assertFileDoesNotExist($root . '/public/assets/admin/admin.js');
        $css = (string)file_get_contents($root . '/public/assets/admin/laiot.css');
        // Yoyo-обёртка на странице не создаёт отдельный блок: иначе компонент
        // из нескольких корней теряет gap страницы и блоки «слипаются».
        self::assertStringContainsString('md-ui-page>.yoyo-wrapper[class=yoyo-wrapper]{display:contents}', $css);
        $bundle = (string)file_get_contents($file);
        foreach ([
            'jQuery JavaScript Library',
            'jquery.com',
            'JQMIGRATE',
            'jquery-migrate',
            'window.$ =',
            'MDJAdminPanel',
            'partLoad',
        ] as $marker) {
            self::assertStringNotContainsString($marker, $bundle, 'В laiot.js не должно быть ' . $marker);
        }
    }

    public function testAdminAndLoginLoadOnlyLaIOTAssets(): void
    {
        $admin = (string)$this->boot(true)->handle(Request::create('/admin', 'GET'))->getContent();
        self::assertStringContainsString('/assets/admin/laiot.css', $admin);
        self::assertStringContainsString('/assets/admin/laiot.js', $admin);
        // После npm run build ссылка должна содержать версию файла, иначе
        // браузер продолжает показывать старый CSS.
        self::assertMatchesRegularExpression('~/assets/admin/laiot\.(css|js)\?v=\d+~', $admin);
        self::assertStringNotContainsString('/assets/admin/admin.js', $admin);
        self::assertStringNotContainsString('/assets/admin/admin.css', $admin);

        $login = (string)$this->boot(false)->handle(Request::create('/login', 'GET'))->getContent();
        self::assertStringContainsString('/assets/admin/laiot.css', $login);
        self::assertStringContainsString('/assets/admin/laiot.js', $login);
        self::assertStringNotContainsString('/assets/admin/admin.js', $login);
        self::assertStringNotContainsString('/assets/admin/admin.css', $login);
    }

    private function boot(bool $login): Application
    {
        $session = new ArraySession();
        if ($login) {
            $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Администратор', 'is_admin' => true]);
        }
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $session,
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();

        SessionFixture::authenticate($app);

        return $app;
    }
}
