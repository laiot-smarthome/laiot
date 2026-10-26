<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\View\BladeRenderer;
use PHPUnit\Framework\TestCase;

final class ShoutboxPageTest extends TestCase
{
    public function testPageRendersInAlphaMarkupWithData(): void
    {
        $html = (new BladeRenderer(dirname(__DIR__, 2)))->render('pages.shoutbox', [
            'TOTAL_SHOUTS' => 2,
            'SHOWN_SHOUTS' => 1,
            'SYSTEM_SHOUTS' => 1,
            'LAST_SHOUT_TIME' => '12:40',
            'LAST_SHOUT_DATE' => '26.10.2026',
            'SHOUT_FEED' => '<div class="feed">feed</div>',
            'SHOUT_HINT' => '<aside class="hint">hint</aside>',
            'SHOUT_CODE' => '<div class="code">code</div>',
        ]);

        self::assertStringContainsString('md-ui-page', $html);
        self::assertStringContainsString('md-kpi', $html);
        self::assertStringContainsString('<div class="feed">feed</div>', $html);
        self::assertStringContainsString('hint', $html);
        self::assertStringContainsString('code', $html);
        self::assertStringContainsString('Shoutbox', $html);
    }
}
