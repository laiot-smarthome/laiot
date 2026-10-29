<?php

declare(strict_types=1);

namespace LaIOT\Providers;

use Illuminate\Container\Container;
use LaIOT\Database\DatabaseManager;
use LaIOT\Foundation\ServiceProvider;
use LaIOT\Services\Logging\ErrorRepository;
use LaIOT\Services\Maintenance\RetentionPruner;
use LaIOT\Services\Worker\BackgroundTaskState;
use LaIOT\Services\Worker\CoreWorker;
use LaIOT\Services\Worker\ProcessLock;
use LaIOT\Services\Worker\Worker;

/**
 * Зависимости фоновых процессов без запуска процессов во время boot.
 *
 * Изменения:
 * 2026-10-27 — retention читает config/retention.php и доступен CLI/worker.
 * 2026-10-27 — рабочие процессы используют общий каталог блокировок установки.
 * 2026-10-26 — worker и состояние регистрируются лениво через контейнер.
 */
final class RuntimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(BackgroundTaskState::class);
        $this->container->bind(ProcessLock::class, fn (): ProcessLock => new ProcessLock($this->app->root() . '/var/locks'));
        $this->container->singleton(Worker::class);
        $this->container->singleton(CoreWorker::class);
        $this->container->singleton(RetentionPruner::class, fn (Container $container): RetentionPruner => new RetentionPruner(
            $container->make(DatabaseManager::class),
            $container->make(ErrorRepository::class),
            (array)$this->app->config('retention', [])
        ));
    }
}
