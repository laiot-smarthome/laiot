<?php

declare(strict_types=1);

use MajorDoMo\Support\AppContext;

/**
 * Пользовательские обёртки, которые уже работают в MyDoMo.
 *
 * Файл подключается только новым bootstrap, не Composer-автолоадом:
 * alpha продолжает использовать свои lib/core/Helpers без конфликта имён.
 */
if (!function_exists('say')) {
    /**
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
