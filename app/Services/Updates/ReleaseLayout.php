<?php

declare(strict_types=1);

namespace LaIOT\Services\Updates;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Релизы содержат только поставку; var, .env и сторонние модули остаются общими.
 * 2026-10-29 — первая смена прямой установки сохраняет прежний код как локальный релиз.
 */
final readonly class ReleaseLayout
{
    public const CONTENT = ['app', 'bin', 'bootstrap', 'config', 'database', 'deploy', 'docs', 'resources', 'routes', 'public', 'vendor', 'composer.json', 'composer.lock'];

    public string $installation;
    public string $storage;
    public string $environment;
    public string $modules;

    public function __construct(public string $root)
    {
        $this->storage = realpath($root . '/var') ?: $root . '/var';
        $saved = UpdateFiles::read($this->storage . '/updates/layout.json');
        $this->installation = (string)($saved['installation'] ?? (basename(dirname($root)) === 'releases' ? dirname($root, 2) : $root));
        $this->environment = (string)($saved['environment'] ?? (realpath($root . '/.env') ?: $root . '/.env'));
        $this->modules = (string)($saved['modules'] ?? $root . '/modules');
    }

    public function initialize(): void
    {
        UpdateFiles::directory($this->installation . '/releases');
        chmod($this->installation . '/releases', 0755);
        UpdateFiles::directory($this->storage . '/updates');
        UpdateFiles::json($this->storage . '/updates/layout.json', [
            'installation' => $this->installation, 'environment' => $this->environment, 'modules' => $this->modules,
        ]);
    }

    public function release(string $id): string
    {
        ReleaseManifest::assertId($id);
        return $this->installation . '/releases/' . $id;
    }

    /** @param array<string, mixed> $manifest */
    public function preserve(array $manifest): string
    {
        $id = (string)$manifest['id'];
        $path = $this->release($id);
        if (realpath($path) === realpath($this->root)) {
            return $path;
        }
        if (is_file($path . '/release.json')) {
            return $path;
        }
        $temporary = $path . '.prepare-' . bin2hex(random_bytes(6));
        UpdateFiles::directory($temporary);
        foreach (self::CONTENT as $name) {
            if (file_exists($this->root . '/' . $name)) {
                $this->copy($this->root . '/' . $name, $temporary . '/' . $name);
            }
        }
        if (is_dir($this->root . '/modules/core')) {
            $this->copy($this->root . '/modules/core', $temporary . '/modules/core');
        }
        UpdateFiles::json($temporary . '/release.json', $manifest);
        $this->attach($temporary);
        chmod($temporary, 0755);
        if (!rename($temporary, $path)) {
            throw new RuntimeException('Не удалось сохранить прежний код системы.');
        }
        UpdateFiles::syncDirectory(dirname($path));
        return $path;
    }

    public function attach(string $path): void
    {
        foreach (['var' => $this->storage, '.env' => $this->environment] as $name => $target) {
            if (!file_exists($path . '/' . $name) && !is_link($path . '/' . $name) && !symlink($target, $path . '/' . $name)) {
                throw new RuntimeException('Не удалось подключить постоянное хранилище релиза.');
            }
        }
        UpdateFiles::directory($path . '/modules');
        foreach (glob($this->modules . '/*', GLOB_ONLYDIR) ?: [] as $module) {
            if (basename($module) === 'core') {
                continue;
            }
            $link = $path . '/modules/' . basename($module);
            if (!is_link($link) && !file_exists($link) && !symlink($module, $link)) {
                throw new RuntimeException('Не удалось подключить сторонний модуль.');
            }
        }
        UpdateFiles::syncDirectory($path . '/modules');
        UpdateFiles::syncDirectory($path);
    }

    public function switchTo(string $id): void
    {
        $path = $this->release($id);
        if (!is_file($path . '/release.json') || !is_file($path . '/vendor/autoload.php')) {
            throw new RuntimeException('Релиз не подготовлен к переключению.');
        }
        UpdateFiles::link($path, $this->installation . '/current');
        clearstatcache(true);
        // У прямой установки nginx продолжает смотреть в прежний public.
        // Стабильные index.php и bin/laiot передают выполнение current.
        if ($this->installation === $this->root || is_file($this->installation . '/public/index.php')) {
            $assets = $this->installation . '/public/assets';
            $saved = $this->storage . '/updates/original-assets';
            if (!is_link($assets) && is_dir($assets) && !is_dir($saved) && !rename($assets, $saved)) {
                throw new RuntimeException('Не удалось сохранить прежнюю статику.');
            }
            if (!is_link($assets)) {
                UpdateFiles::link($this->installation . '/current/public/assets', $assets);
            }
        }
    }

    private function copy(string $source, string $target): void
    {
        if (is_file($source)) {
            UpdateFiles::directory(dirname($target));
            if (!copy($source, $target)) {
                throw new RuntimeException('Не удалось сохранить файл прежнего релиза.');
            }
            chmod($target, 0644);
            UpdateFiles::syncFile($target);
            return;
        }
        UpdateFiles::directory($target);
        $directories = [$target];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($source) + 1);
            if ($file->isLink()) {
                throw new RuntimeException('Нельзя сохранять код с символическими ссылками: ' . $relative);
            }
            if ($file->isDir()) {
                UpdateFiles::directory($target . '/' . $relative);
                chmod($target . '/' . $relative, 0755);
                $directories[] = $target . '/' . $relative;
            } else {
                $this->copy($file->getPathname(), $target . '/' . $relative);
            }
        }
        chmod($target, 0755);
        foreach (array_reverse($directories) as $directory) {
            UpdateFiles::syncDirectory($directory);
        }
    }
}
