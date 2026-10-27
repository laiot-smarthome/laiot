<?php

declare(strict_types=1);

namespace MajorDoMo\Foundation\Providers;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use LogicException;
use MajorDoMo\Cache\CacheFactory;
use MajorDoMo\Cache\CacheStore;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Events\PropertyEventRepository;
use MajorDoMo\Foundation\ServiceProvider;
use MajorDoMo\Logging\ErrorFileWriter;
use MajorDoMo\Logging\ErrorRepository;
use MajorDoMo\Logging\LoggerFactory;
use MajorDoMo\Logging\RequestContext;
use MajorDoMo\Queue\DatabaseQueue;
use MajorDoMo\Queue\JobRegistry;
use MajorDoMo\Queue\Queue;
use MajorDoMo\Support\Clock;
use MajorDoMo\Support\HelperLoader;
use MajorDoMo\Support\MaintenanceMode;
use Psr\Log\LoggerInterface;

/**
 * Базовое окружение: время, логирование, БД, кэш, очередь и helpers.
 *
 * Изменения:
 * 2026-10-27 — аварии очереди связаны с журналом шагов и просмотром ошибок.
 * 2026-10-26 — инфраструктура вынесена из Application в ленивые singleton.
 */
final class InfrastructureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(Clock::class, function (): Clock {
            $timezone = (string)$this->app->config('timezone', 'Europe/Moscow');

            return new Clock(in_array($timezone, timezone_identifiers_list(), true)
                ? $timezone : date_default_timezone_get());
        });
        $this->container->singleton(HelperLoader::class, fn (): HelperLoader => new HelperLoader(
            $this->app->root() . '/src/Support/helpers'
        ));
        $this->container->singleton(RequestContext::class);
        $this->container->singleton(LoggerFactory::class, function (Container $container): LoggerFactory {
            $logging = (array)$this->app->config('logging', []);

            return new LoggerFactory([
                'path' => (string)$this->app->config('log', $logging['path'] ?? 'php://stderr'),
                'directory' => (string)($logging['directory'] ?? ''),
                'max_files' => (int)($logging['max_files'] ?? 14),
                'level' => (string)($logging['level'] ?? 'info'),
                'debug' => (bool)$this->app->config('debug', false),
            ], $container->make(RequestContext::class));
        });
        $this->container->singleton(ErrorFileWriter::class, fn (): ErrorFileWriter => new ErrorFileWriter(
            (string)$this->app->config('logging.error_directory', $this->app->root() . '/var/log/errors')
        ));
        $this->container->singleton(LoggerInterface::class, static fn (Container $container): LoggerInterface => $container
            ->make(LoggerFactory::class)->channel('app'));
        $this->container->singleton(DatabaseManager::class, fn (): DatabaseManager => new DatabaseManager(
            (array)$this->app->config('database', [])
        ));
        $this->container->singleton(CacheStore::class, fn (Container $container): CacheStore => CacheFactory::create(
            (array)$this->app->config('cache', []),
            $container->make(DatabaseManager::class)
        ));
        $this->container->singleton(Queue::class, fn (Container $container): Queue => new DatabaseQueue(
            $container->make(DatabaseManager::class),
            (array)$this->app->config('queue', []),
            null,
            $container->make(ErrorRepository::class),
            $container->make(PropertyEventRepository::class)
        ));
        $this->container->singleton(JobRegistry::class);
        $this->container->singleton(MaintenanceMode::class, fn (): MaintenanceMode => new MaintenanceMode($this->app->root()));
        $this->container->singleton(Dispatcher::class, static fn (Container $container): Dispatcher => new Dispatcher($container));
    }

    public function boot(): void
    {
        foreach (['var', 'var/cache', 'var/log'] as $directory) {
            $path = $this->app->root() . '/' . $directory;
            if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
                throw new LogicException('Не удалось создать runtime-каталог: ' . $path);
            }
        }
        date_default_timezone_set($this->container->make(Clock::class)->timezone());
        $this->container->make(HelperLoader::class)->loadCore();
    }
}
