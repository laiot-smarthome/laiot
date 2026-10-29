<?php

declare(strict_types=1);

namespace LaIOT\Support;

use RuntimeException;

/**
 * Автоподключение пользовательских helper-файлов.
 *
 * Core: `app/Support/helpers/*.php`; модуль: `helpers` из module.json или
 * `helpers.php` в корне модуля. Никаких ручных require в bootstrap.
 */
final class HelperLoader
{
    /** @var array<string, true> */
    private array $loaded = [];

    public function __construct(private readonly string $corePath)
    {
    }

    public function loadCore(): void
    {
        foreach (glob($this->corePath . '/*.php') ?: [] as $file) {
            $this->load($file);
        }
    }

    public function loadModule(string $moduleId, string $modulePath, mixed $declared): void
    {
        $files = [];
        if (is_string($declared) && $declared !== '') {
            $files[] = $declared;
        } elseif (is_array($declared)) {
            foreach ($declared as $file) {
                if (is_string($file) && $file !== '') {
                    $files[] = $file;
                }
            }
        } elseif (is_file($modulePath . '/helpers.php')) {
            $files[] = 'helpers.php';
        }

        foreach ($files as $relative) {
            if (str_contains($relative, '..')) {
                throw new RuntimeException('Некорректный путь helpers модуля ' . $moduleId);
            }
            $file = $modulePath . '/' . $relative;
            if (!is_file($file)) {
                throw new RuntimeException('Файл helpers модуля не найден: ' . $file);
            }
            $this->load($file);
        }
    }

    private function load(string $file): void
    {
        $real = realpath($file);
        if ($real === false || isset($this->loaded[$real])) {
            return;
        }
        $this->loaded[$real] = true;
        require_once $real;
    }
}
