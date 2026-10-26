<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

/**
 * Новый admin-shell не должен тянуть jQuery.
 *
 * Старый alpha-бандл admin.js остаётся для /panel/, но layout и вход нового
 * ядра обязаны грузить mydomo.js.
 */
final class AdminAssetsTest extends TestCase
{
    public function testMyDoMoBundleHasNoJqueryOrAlphaRuntime(): void
    {
        $root = dirname(__DIR__, 2);
        $file = $root . '/public/assets/admin/mydomo.js';
        self::assertFileExists($file);
        self::assertFileExists($root . '/public/assets/admin/mydomo.css');
        self::assertFileDoesNotExist($root . '/public/assets/admin/admin.js');
        $bundle = (string)file_get_contents($file);
        foreach ([
            'jQuery JavaScript Library',
            'jquery.com',
            'JQMIGRATE',
            'jquery-migrate',
            'window.$ =',
            'MDJAdminPanel',
            'partLoad',
            'TomSelect',
        ] as $marker) {
            self::assertStringNotContainsString($marker, $bundle, 'В mydomo.js не должно быть ' . $marker);
        }
    }

    public function testAdminAndLoginLoadOnlyMyDoMoAssets(): void
    {
        $admin = (string)$this->boot(true)->handle(Request::create('/admin', 'GET'))->getContent();
        self::assertStringContainsString('/assets/admin/mydomo.css', $admin);
        self::assertStringContainsString('/assets/admin/mydomo.js', $admin);
        self::assertStringNotContainsString('/assets/admin/admin.js', $admin);
        self::assertStringNotContainsString('/assets/admin/admin.css', $admin);

        $login = (string)$this->boot(false)->handle(Request::create('/login', 'GET'))->getContent();
        self::assertStringContainsString('/assets/admin/mydomo.css', $login);
        self::assertStringContainsString('/assets/admin/mydomo.js', $login);
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

        return $app;
    }
}
