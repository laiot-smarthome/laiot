<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Carbon\CarbonImmutable;
use LaIOT\Support\Clock;
use PHPUnit\Framework\TestCase;

final class ClockTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function testClockUsesConfiguredTimezoneAndFreezes(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-26 12:00:00', 'UTC'));
        $clock = new Clock('Europe/Moscow');

        self::assertSame('Europe/Moscow', $clock->timezone());
        self::assertSame('2026-10-26 15:00:00', $clock->nowString());
    }
}
