<?php

declare(strict_types=1);

use LaIOT\Services\Xray\XrayJournal;
use LaIOT\Support\AppContext;

// 2026-10-30 — обе обёртки пишут значения в X-ray без вывода и остановки кода.
if (!function_exists('debmes')) {
    function debmes(mixed ...$values): mixed
    {
        $app = AppContext::get();
        if ($app === null) {
            throw new LogicException('debmes() требует загруженное ядро LaIOT.');
        }
        foreach ($values as $key => $value) {
            $app->container()->make(XrayJournal::class)->dump($value, is_string($key) ? $key : '');
        }

        return count($values) === 1 ? reset($values) : ($values === [] ? null : $values);
    }
}

if (!function_exists('dump')) {
    function dump(mixed ...$values): mixed
    {
        return debmes(...$values);
    }
}
