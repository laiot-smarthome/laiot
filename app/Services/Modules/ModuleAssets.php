<?php

declare(strict_types=1);

namespace LaIOT\Services\Modules;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/**
 * Публикация статики модуля.
 *
 * Исходники модуля лежат в `resources/assets` (или пути из манифеста), а
 * веб-сервер отдаёт только `public/`. При установке и включении каталог
 * копируется в `public/assets/modules/<id>`; при удалении — убирается.
 * Никаких символических ссылок наружу `public/`.
 *
 * 2026-10-27 — публикация ассетов модулей вместо ручного копирования.
 */
final readonly class ModuleAssets
{
    public function __construct(
        private string $root,
        private Filesystem $files = new Filesystem()
    ) {
    }

    /**
     * @return int Число скопированных файлов.
     */
    public function publish(string $moduleId, string $modulePath, string $relative = 'assets'): int
    {
        $this->assertId($moduleId);
        if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, '..')) {
            throw new RuntimeException('Некорректный путь assets модуля.');
        }
        $source = rtrim($modulePath, '/\\') . DIRECTORY_SEPARATOR . $relative;
        if (!is_dir($source)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $count++;
            }
        }

        $target = $this->target($moduleId);
        if (is_dir($target)) {
            $this->files->deleteDirectory($target);
        }
        $this->files->copyDirectory($source, $target);

        return $count;
    }

    public function remove(string $moduleId): bool
    {
        $this->assertId($moduleId);
        $target = $this->target($moduleId);
        if (!is_dir($target)) {
            return false;
        }

        return (bool)$this->files->deleteDirectory($target);
    }

    public function target(string $moduleId): string
    {
        $this->assertId($moduleId);

        return rtrim($this->root, '/\\') . '/public/assets/modules/' . $moduleId;
    }

    private function assertId(string $moduleId): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $moduleId)) {
            throw new RuntimeException('Некорректный id модуля для assets.');
        }
    }
}
