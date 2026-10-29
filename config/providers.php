<?php

declare(strict_types=1);

use LaIOT\Providers\DomainServiceProvider;
use LaIOT\Providers\EncryptionServiceProvider;
use LaIOT\Providers\FilesServiceProvider;
use LaIOT\Providers\HttpServiceProvider;
use LaIOT\Providers\InfrastructureServiceProvider;
use LaIOT\Providers\InstallServiceProvider;
use LaIOT\Providers\ModuleServiceProvider;
use LaIOT\Providers\RuntimeServiceProvider;
use LaIOT\Providers\UpdateServiceProvider;
use LaIOT\Providers\ValidationServiceProvider;
use LaIOT\Providers\ViewServiceProvider;
use LaIOT\Providers\WebSocketServiceProvider;

// 2026-10-26 — порядок boot подсистем; register сначала выполняется у всех.
// 2026-10-28 — общий валидатор доступен до запуска HTTP и SDK-модулей.
return [
    InfrastructureServiceProvider::class,
    EncryptionServiceProvider::class,
    FilesServiceProvider::class,
    ValidationServiceProvider::class,
    WebSocketServiceProvider::class,
    DomainServiceProvider::class,
    ViewServiceProvider::class,
    HttpServiceProvider::class,
    ModuleServiceProvider::class,
    RuntimeServiceProvider::class,
    InstallServiceProvider::class,
    UpdateServiceProvider::class,
];
