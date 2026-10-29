<?php

declare(strict_types=1);

namespace LaIOT\Services\Files;

use InvalidArgumentException;
use League\Flysystem\DirectoryListing;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use League\Flysystem\WhitespacePathNormalizer;
use RuntimeException;

/**
 * Ограниченный локальный диск поверх Flysystem: только относительные пути.
 *
 * DISALLOW_LINKS в Flysystem защищает обход каталога, но не прямое чтение
 * и запись через ссылку. Поэтому перед операцией проверяется вся цепочка пути.
 * Это граница SDK, а не sandbox для произвольного доверенного PHP.
 *
 * Изменения:
 * 2026-10-28 — запрет выхода через .., абсолютные пути и символические ссылки.
 */
final class LocalFiles
{
    private readonly Filesystem $filesystem;

    public function __construct(private readonly string $root)
    {
        $visibility = PortableVisibilityConverter::fromArray([
            'file' => ['public' => 0600, 'private' => 0600],
            'dir' => ['public' => 0700, 'private' => 0700],
        ], Visibility::PRIVATE);
        $this->filesystem = new Filesystem(new LocalFilesystemAdapter(
            $root,
            $visibility,
            LOCK_EX,
            LocalFilesystemAdapter::DISALLOW_LINKS,
            lazyRootCreation: true
        ), ['visibility' => Visibility::PRIVATE, 'directory_visibility' => Visibility::PRIVATE]);
    }

    public function write(string $path, string $contents): void
    {
        $this->filesystem->write($this->path($path), $contents);
    }

    /** @param resource $stream */
    public function writeStream(string $path, $stream): void
    {
        $this->filesystem->writeStream($this->path($path), $stream);
    }

    public function read(string $path): string
    {
        return $this->filesystem->read($this->path($path));
    }

    /** @return resource */
    public function readStream(string $path)
    {
        return $this->filesystem->readStream($this->path($path));
    }

    public function fileExists(string $path): bool
    {
        return $this->filesystem->fileExists($this->path($path));
    }

    public function directoryExists(string $path = ''): bool
    {
        return $this->filesystem->directoryExists($this->path($path));
    }

    /** @return DirectoryListing<StorageAttributes> */
    public function listContents(string $path = '', bool $deep = false): DirectoryListing
    {
        return $this->filesystem->listContents($this->path($path), $deep);
    }

    public function createDirectory(string $path): void
    {
        $this->filesystem->createDirectory($this->path($path));
    }

    public function delete(string $path): void
    {
        $this->filesystem->delete($this->path($path));
    }

    public function deleteDirectory(string $path): void
    {
        $path = $this->path($path);
        // Проверка обхода завершается до удаления: ссылка внутри дерева
        // не должна приводить к частичной очистке или затрагивать чужие данные.
        foreach ($this->filesystem->listContents($path, true) as $entry) {
            $this->path($entry->path());
        }
        $this->filesystem->deleteDirectory($path);
    }

    public function copy(string $source, string $destination): void
    {
        $this->filesystem->copy($this->path($source), $this->path($destination));
    }

    public function move(string $source, string $destination): void
    {
        $this->filesystem->move($this->path($source), $this->path($destination));
    }

    public function fileSize(string $path): int
    {
        return $this->filesystem->fileSize($this->path($path));
    }

    public function mimeType(string $path): string
    {
        return $this->filesystem->mimeType($this->path($path));
    }

    public function lastModified(string $path): int
    {
        return $this->filesystem->lastModified($this->path($path));
    }

    private function path(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, '/') || str_contains($path, ':')
            || in_array('..', explode('/', $path), true)) {
            throw new InvalidArgumentException('Путь должен находиться внутри файлового каталога.');
        }
        $path = (new WhitespacePathNormalizer())->normalizePath($path);
        $current = rtrim($this->root, '/\\') . ($path === '' ? '' : '/' . $path);
        do {
            clearstatcache(true, $current);
            if (is_link($current)) {
                throw new RuntimeException('Символические ссылки в файловом хранилище запрещены.');
            }
            $parent = dirname($current);
            $finished = $parent === $current;
            $current = $parent;
        } while (!$finished);

        return $path;
    }
}
