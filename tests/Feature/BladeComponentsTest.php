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
        self::assertStringNotContainsString('component not found', $html);
    }
}
