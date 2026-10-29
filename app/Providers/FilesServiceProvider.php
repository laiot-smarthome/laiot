<?php

declare(strict_types=1);

namespace LaIOT\Providers;

use LaIOT\Foundation\ServiceProvider;
use LaIOT\Services\Files\FileStorage;
use LaIOT\Services\Files\LocalFiles;

/**
 * Локальное хранилище данных, отдельное от runtime-кэша и кода модулей.
 *
 * Изменения:
 * 2026-10-28 — диски ядра и модулей в var/files без записи при boot.
 */
final class FilesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(FileStorage::class, fn (): FileStorage => new FileStorage(
            (string)$this->app->config('files.root')
        ));
        $this->container->singleton(LocalFiles::class, fn (): LocalFiles => $this->container
            ->make(FileStorage::class)->core());
    }
}
