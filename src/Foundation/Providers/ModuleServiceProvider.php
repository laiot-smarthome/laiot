<?php

declare(strict_types=1);

namespace MajorDoMo\Foundation\Providers;

use Illuminate\Container\Container;
use Illuminate\Routing\Router;
use Illuminate\View\Factory;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Foundation\ServiceProvider;
use MajorDoMo\Modules\ModuleAssets;
use MajorDoMo\Modules\ModuleInstaller;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Modules\ModulePermissions;
use MajorDoMo\Modules\PropertySubscriber;
use MajorDoMo\Modules\PropertySubscriberRegistry;
use MajorDoMo\Support\HelperLoader;

/**
 * Обнаружение SDK-модулей и подключение их маршрутов/представлений.
 *
 * Изменения:
 * 2026-10-27 — регистрация обработчиков изменений свойств из манифестов.
 * 2026-10-26 — модули запускаются после регистрации зависимостей всех провайдеров.
 */
final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(PropertySubscriberRegistry::class);
        $this->container->singleton(ModulePermissions::class);
        $this->container->singleton(ModuleAssets::class, fn (): ModuleAssets => new ModuleAssets($this->app->root()));
        $this->container->singleton(ModuleManager::class, fn (Container $container): ModuleManager => new ModuleManager(
            $this->app->root(),
            $container->make(DatabaseManager::class),
            $container,
            $container->make(HelperLoader::class)
        ));
        $this->container->singleton(ModuleInstaller::class, fn (Container $container): ModuleInstaller => new ModuleInstaller(
            $container->make(ModuleManager::class),
            (string)$this->app->config('version', '0.0.0'),
            $container->make(ModulePermissions::class),
            $container->make(ModuleAssets::class)
        ));
    }

    public function boot(): void
    {
        $modules = $this->container->make(ModuleManager::class);
        $modules->register(
            $this->container->make(Router::class),
            $this->container->make(Factory::class)
        );
        $this->registerPropertySubscribers($modules);
    }

    /**
     * Подключает объявленные модулями обработчики изменений свойств.
     *
     * Манифест задаёт только класс; конкретные объекты и свойства выбирает
     * пользователь и хранит в `property_subscriptions`. Обработчик создаётся
     * лениво: модуль не грузится, пока на его свойство никто не подписан.
     */
    private function registerPropertySubscribers(ModuleManager $modules): void
    {
        $registry = $this->container->make(PropertySubscriberRegistry::class);
        foreach ($modules->all() as $id => $module) {
            $short = $module['property_subscriber'] ?? null;
            if (!is_string($short) || $short === '') {
                continue;
            }
            /** @var class-string<PropertySubscriber> $class */
            $class = (string)$module['namespace'] . $short;
            $registry->register(
                (string)$id,
                static fn (): PropertySubscriber => $modules->service((string)$id, $class)
            );
        }
    }
}
