<?php

declare(strict_types=1);

use MajorDoMo\Foundation\Providers\DomainServiceProvider;
use MajorDoMo\Foundation\Providers\EncryptionServiceProvider;
use MajorDoMo\Foundation\Providers\FilesServiceProvider;
use MajorDoMo\Foundation\Providers\HttpServiceProvider;
use MajorDoMo\Foundation\Providers\InfrastructureServiceProvider;
use MajorDoMo\Foundation\Providers\InstallServiceProvider;
use MajorDoMo\Foundation\Providers\ModuleServiceProvider;
use MajorDoMo\Foundation\Providers\RuntimeServiceProvider;
use MajorDoMo\Foundation\Providers\ValidationServiceProvider;
use MajorDoMo\Foundation\Providers\ViewServiceProvider;

// 2026-10-26 — порядок boot подсистем; register сначала выполняется у всех.
// 2026-10-28 — общий валидатор доступен до запуска HTTP и SDK-модулей.
return [
    InfrastructureServiceProvider::class,
    EncryptionServiceProvider::class,
    FilesServiceProvider::class,
    ValidationServiceProvider::class,
    DomainServiceProvider::class,
    ViewServiceProvider::class,
    HttpServiceProvider::class,
    ModuleServiceProvider::class,
    RuntimeServiceProvider::class,
    InstallServiceProvider::class,
];
