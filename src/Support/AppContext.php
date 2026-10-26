<?php

declare(strict_types=1);

namespace MajorDoMo\Support;

use MajorDoMo\Foundation\Application;
use Throwable;

/**
 * Доступ к текущему Application для пользовательских обёрток.
 *
 * Это не service locator для нового кода: только compatibility-слой для
 * глобальных функций, которые вызывает пользовательский PHP.
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

    /**
     * `say()` пишет в Shoutbox core-модуля. Пока это единственная
     * пользовательская обёртка, которая работает в новом ядре.
     */
    public static function say(string $message, int $memberId = 0, string $source = 'say'): void
    {
        $message = trim($message);
        if ($message === '' || self::$app === null) {
            return;
        }
        $message = mb_substr($message, 0, 255);

        try {
            self::$app->container()->make(\MajorDoMo\Notifications\NotificationService::class)
                ->send($source, $message, 'info');
        } catch (Throwable $error) {
            self::$app->logger()->warning('say() не записал сообщение', [
                'error' => $error->getMessage(),
            ]);
        }
    }
}
