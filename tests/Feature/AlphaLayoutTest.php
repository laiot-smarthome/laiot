<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\View\BladeRenderer;
use PHPUnit\Framework\TestCase;

final class AlphaLayoutTest extends TestCase
{
    public function testLayoutUsesAlphaShellClasses(): void
    {
        $html = (new BladeRenderer(dirname(__DIR__, 2)))->render('layouts.alpha', [
            'title' => 'Оповещения',
            'content' => '<div class="page-content">content</div>',
            'csrf' => 'token',
            'user' => ['id' => 1, 'username' => 'admin', 'name' => 'Админ', 'is_admin' => true],
            'active' => 'notifications',
            'menu' => [
                ['label' => 'Система', 'items' => [
                    ['id' => 'notifications', 'label' => 'Оповещения', 'href' => '/notifications', 'icon' => 'ti-message'],
                ]],
            ],
        ]);

        self::assertStringContainsString('md-admin-shell', $html);
        self::assertStringContainsString('md-admin-topbar', $html);
        self::assertStringContainsString('md-admin-sidebar', $html);
        self::assertStringContainsString('md-admin-main', $html);
        self::assertStringContainsString('Оповещения', $html);
        self::assertStringContainsString('page-content', $html);
        self::assertStringContainsString('assets/admin/laiot.css', $html);
        self::assertStringContainsString('assets/admin/laiot.js', $html);
        self::assertStringNotContainsString('assets/admin/admin.css', $html);
        self::assertStringNotContainsString('assets/admin/admin.js', $html);
    }
}
