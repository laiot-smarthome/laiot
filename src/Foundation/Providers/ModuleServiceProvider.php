<?php

declare(strict_types=1);

namespace MajorDoMo\Foundation\Providers;

use Illuminate\Container\Container;
use Illuminate\Routing\Router;
use Illuminate\View\Factory;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Foundation\ServiceProvider;
use MajorDoMo\Modules\ModuleInstaller;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Support\HelperLoader;

/**
 * Обнаружение SDK-модулей и подключение их маршрутов/представлений.
 *
 * Изменения:
 * 2026-10-26 — модули запускаются после регистрации зависимостей всех провайдеров.
 */
final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(ModuleManager::class, fn (Container $container): ModuleManager => new ModuleManager(
            $this->app->root(),
            $container->make(DatabaseManager::class),
            $container,
            $container->make(HelperLoader::class)
        ));
        $this->container->singleton(ModuleInstaller::class, fn (Container $container): ModuleInstaller => new ModuleInstaller(
            $container->make(ModuleManager::class),
            (string)$this->app->config('version', '0.0.0')
        ));
    }

    public function boot(): void
    {
        $this->container->make(ModuleManager::class)->register(
            $this->container->make(Router::class),
            $this->container->make(Factory::class)
        );
    }
}
