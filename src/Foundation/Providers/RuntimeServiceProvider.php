<?php

declare(strict_types=1);

namespace MajorDoMo\Foundation\Providers;

use Illuminate\Container\Container;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Foundation\ServiceProvider;
use MajorDoMo\Logging\ErrorRepository;
use MajorDoMo\Maintenance\RetentionPruner;
use MajorDoMo\Worker\BackgroundTaskState;
use MajorDoMo\Worker\CoreWorker;
use MajorDoMo\Worker\ProcessLock;
use MajorDoMo\Worker\Worker;

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
