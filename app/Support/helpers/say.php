<?php

declare(strict_types=1);

use LaIOT\Services\Notifications\SayService;
use LaIOT\Support\AppContext;

// 2026-10-29 — важность задаётся явно, источник определяется по месту вызова.

if (!function_exists('say')) {
    /**
     * Сохранить сообщение системы и поставить доставку подписчикам в очередь.
     *
     * @return array{status:bool,id?:int,error?:string}
     */
    function say(string $message, int $importance = 5): array
    {
        $app = AppContext::get();
        if ($app === null) {
            return ['status' => false, 'error' => 'Приложение не загружено.'];
        }

        return $app->container()->make(SayService::class)->say($message, $importance);
    }
}
