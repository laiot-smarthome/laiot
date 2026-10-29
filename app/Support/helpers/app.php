<?php

declare(strict_types=1);

use LaIOT\Foundation\Application;
use LaIOT\Support\AppContext;

if (!function_exists('app')) {
    function app(): ?Application
    {
        return AppContext::get();
    }
}
