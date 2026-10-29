<?php

declare(strict_types=1);

namespace LaIOT\Support;

use LaIOT\Foundation\Application;

/**
 * Доступ к текущему Application для пользовательских обёрток.
 *
 * Хранит контекст глобальных функций пользовательского PHP.
 *
 * 2026-10-29 — say делегирует общему сервису напрямую из helper.
 */
final class AppContext
{
    private static ?Application $app = null;

    public static function set(Application $app): void
    {
        self::$app = $app;
    }

    public static function get(): ?Application
    {
        return self::$app;
    }

    public static function reset(): void
    {
        self::$app = null;
    }
}
