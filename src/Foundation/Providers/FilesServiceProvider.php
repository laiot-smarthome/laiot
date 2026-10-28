<?php

declare(strict_types=1);

namespace MajorDoMo\Foundation\Providers;

use MajorDoMo\Files\FileStorage;
use MajorDoMo\Files\LocalFiles;
use MajorDoMo\Foundation\ServiceProvider;

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
