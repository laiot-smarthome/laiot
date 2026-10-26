<?php

declare(strict_types=1);

use MajorDoMo\Modules\Core\Shoutbox\ShoutboxService;
use MajorDoMo\Support\AppContext;

if (!function_exists('shoutbox_latest')) {
    /**
     * Публичный helper модуля: последние сообщения Shoutbox.
     *
     * @return list<array{id:int,message:string,created_at:string,source:string}>
     */
    function shoutbox_latest(int $limit = 10): array
    {
        $app = AppContext::get();
        if ($app === null) {
            return [];
        }

        return $app->modules()
            ->service('shoutbox', ShoutboxService::class)
            ->latest($limit);
    }
}
