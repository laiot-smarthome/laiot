<?php

declare(strict_types=1);

use MajorDoMo\Foundation\Application;
use MajorDoMo\Support\AppContext;

if (!function_exists('app')) {
    function app(): ?Application
    {
        return AppContext::get();
    }
}
