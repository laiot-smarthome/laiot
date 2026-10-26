<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\View\BladeRenderer;
use PHPUnit\Framework\TestCase;

final class AlphaLayoutTest extends TestCase
{
    public function testLayoutUsesAlphaShellClasses(): void
    {
        $html = (new BladeRenderer(dirname(__DIR__, 2)))->render('layouts.alpha', [
            'title' => 'Shoutbox',
            'content' => '<div class="page-content">content</div>',
            'csrf' => 'token',
            'user' => ['id' => 1, 'username' => 'admin', 'name' => 'Админ', 'is_admin' => true],
            'active' => 'shoutbox',
            'menu' => [
                ['label' => 'Система', 'items' => [
                    ['id' => 'shoutbox', 'label' => 'Shoutbox', 'href' => '/module/shoutbox', 'icon' => 'ti-message'],
                ]],
            ],
        ]);

        self::assertStringContainsString('md-admin-shell', $html);
        self::assertStringContainsString('md-admin-topbar', $html);
        self::assertStringContainsString('md-admin-sidebar', $html);
        self::assertStringContainsString('md-admin-main', $html);
        self::assertStringContainsString('Shoutbox', $html);
        self::assertStringContainsString('page-content', $html);
        self::assertStringContainsString('assets/admin/admin.css', $html);
    }
}
