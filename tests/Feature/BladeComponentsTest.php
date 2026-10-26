<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\View\BladeRenderer;
use PHPUnit\Framework\TestCase;

final class BladeComponentsTest extends TestCase
{
    public function testAlphaComponentsRenderWithBlade(): void
    {
        $html = (new BladeRenderer(dirname(__DIR__, 2)))->render('ui-kit.blade');

        self::assertStringContainsString('md-btn md-btn--primary', $html);
        self::assertStringContainsString('md-card', $html);
        self::assertStringContainsString('Карточка на новом движке', $html);
        self::assertStringContainsString('Сохранить', $html);
        self::assertStringContainsString('md-alert md-alert--info', $html);
        self::assertStringContainsString('md-kpi', $html);
        self::assertStringContainsString('md-status--online', $html);
        self::assertStringContainsString('md-avatar', $html);
        self::assertStringContainsString('md-progress', $html);
        self::assertStringContainsString('md-empty', $html);
        self::assertStringContainsString('md-input', $html);
        self::assertStringContainsString('md-select', $html);
        self::assertStringContainsString('md-textarea', $html);
        self::assertStringContainsString('md-code-editor', $html);
        self::assertStringContainsString('x-model="code"', $html);
        self::assertStringContainsString('md-check', $html);
        self::assertStringContainsString('md-switch', $html);
        self::assertStringContainsString('md-disclosure', $html);
        self::assertStringContainsString('md-skeleton', $html);
        self::assertStringContainsString('md-modal', $html);
        self::assertStringContainsString('md-admin-drawer', $html);
        self::assertStringContainsString('md-activity', $html);
        self::assertStringContainsString('Лента событий', $html);
        self::assertStringContainsString('md-hint', $html);
        self::assertStringContainsString('Blade + Alpine', $html);
        self::assertStringContainsString('$store.mdModal', $html);
        self::assertStringContainsString('$store.mdDrawer', $html);
        self::assertStringNotContainsString('data-bs-dismiss="modal"', $html);
        self::assertStringNotContainsString('data-md-drawer-close', $html);
        self::assertStringNotContainsString('component not found', $html);
    }
}
