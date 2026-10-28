<?php

declare(strict_types=1);

namespace MajorDoMo\Foundation\Providers;

use MajorDoMo\Foundation\ServiceProvider;
use MajorDoMo\Install\InstallService;

/**
 * Установщик как сервис приложения.
 *
 * Отдельный провайдер нужен, чтобы страница `/install` и команда `install`
 * получали один экземпляр с одним корнем установки, а тесты могли подменить
 * каталог `.env` и `var` через контейнер.
 *
 * 2026-10-28 — регистрация InstallService.
 */
final class InstallServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            InstallService::class,
            fn (): InstallService => new InstallService($this->app, $this->app->root())
        );
    }
}
