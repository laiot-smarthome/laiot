<?php

declare(strict_types=1);

namespace MajorDoMo\Yoyo;

use Clickfwd\Yoyo\Component;
use FilesystemIterator;
use MajorDoMo\Auth\Authorization;
use MajorDoMo\Foundation\Application;
use UnexpectedValueException;

/**
 * Выбор каталога проекта для форм настроек.
 *
 * 2026-10-27 — добавлена навигация с проверкой прав и границ проекта.
 */
final class FolderPicker extends Component
{
    public string $name = 'folder';
    public string $path = '';
    public string $selected = '';
    public bool $open = false;

    /** @var list<string> */
    protected $props = ['name', 'path', 'selected', 'open'];

    private ?Authorization $authorization = null;
    private string $root = '';

    public function mount(Authorization $authorization, Application $app, string $name = 'folder'): void
    {
        $this->authorization = $authorization;
        $this->root = realpath($app->root()) ?: '';
        $this->name = $name;
    }

    public function show(): void
    {
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function enter(string $encoded): void
    {
        if ($this->authorization?->can('settings.manage') !== true) {
            return;
        }
        $name = base64_decode($encoded, true);
        if ($name === false || $name === '' || str_contains($name, '/') || str_contains($name, '\\')) {
            return;
        }
        $candidate = ltrim($this->path . '/' . $name, '/');
        if ($this->resolve($candidate) !== null) {
            $this->path = $candidate;
        }
    }

    public function up(): void
    {
        $parts = explode('/', $this->path);
        array_pop($parts);
        $this->path = implode('/', $parts);
    }

    public function choose(): void
    {
        if ($this->authorization?->can('settings.manage') !== true) {
            return;
        }
        $selected = $this->resolve($this->path);
        if ($selected !== null) {
            $this->selected = $selected;
            $this->open = false;
        }
    }

    public function render(): mixed
    {
        $allowed = $this->authorization?->can('settings.manage') === true;
        $directory = $allowed ? $this->resolve($this->path) : null;
        $entries = [];
        $error = null;
        if ($this->open && $allowed && $directory !== null) {
            try {
                foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $item) {
                    $name = $item->getFilename();
                    if (!$item->isDir() || $item->isLink() || $this->isExcluded($name)) {
                        continue;
                    }
                    $entries[] = $name;
                }
                natcasesort($entries);
                $entries = array_values($entries);
            } catch (UnexpectedValueException) {
                $error = 'Не удалось прочитать каталог.';
            }
        } elseif ($this->open && $allowed) {
            $error = 'Каталог недоступен или находится за пределами проекта.';
        }

        return $this->view('folder-picker', ['entries' => $entries, 'allowed' => $allowed, 'error' => $error, 'name' => $this->name, 'path' => $this->path, 'selected' => $this->selected, 'open' => $this->open]);
    }

    private function resolve(string $path): ?string
    {
        if ($this->root === '' || str_contains($path, '\\')) {
            return null;
        }
        $candidate = $this->root;
        foreach (array_filter(explode('/', $path), static fn (string $part): bool => $part !== '') as $part) {
            if ($this->isExcluded($part)) {
                return null;
            }
            $candidate .= DIRECTORY_SEPARATOR . $part;
            if (is_link($candidate)) {
                return null;
            }
        }
        $resolved = realpath($candidate);
        if ($resolved === false || !is_dir($resolved)) {
            return null;
        }
        if ($resolved !== $this->root && !str_starts_with($resolved, $this->root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $resolved;
    }

    private function isExcluded(string $name): bool
    {
        return str_starts_with($name, '.') || in_array($name, ['vendor', 'node_modules'], true);
    }
}
