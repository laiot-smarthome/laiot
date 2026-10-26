<?php

declare(strict_types=1);

use MajorDoMo\Support\AppContext;

if (!function_exists('say')) {
    /**
     * Сообщение в Shoutbox. `level` пока не используется.
     *
     * @param mixed $ph
     * @param mixed $level
     * @param mixed $member_id
     * @param mixed $source
     */
    function say($ph, $level = 0, $member_id = 0, $source = ''): void
    {
        AppContext::say((string)$ph, (int)$member_id, (string)($source !== '' ? $source : 'say'));
    }
}
