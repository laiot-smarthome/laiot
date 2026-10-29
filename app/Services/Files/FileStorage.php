<?php

declare(strict_types=1);

namespace LaIOT\Services\Files;

use InvalidArgumentException;

/**
 * Выдаёт отдельные корни файлов: ядро и одна папка на id SDK-модуля.
 *
 * Изменения:
 * 2026-10-28 — файлы модулей не смешиваются с кодом и служебным var.
 */
final readonly class FileStorage
{
    public function __construct(private string $root)
    {
        if (preg_match('~^(?:/|[A-Za-z]:/)~', str_replace('\\', '/', $root)) !== 1) {
            throw new InvalidArgumentException('Корень файлового хранилища должен быть абсолютным путём.');
        }
    }

    public function core(): LocalFiles
    {
        return new LocalFiles($this->root . '/core');
    }

    public function module(string $id): LocalFiles
    {
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $id) !== 1) {
            throw new InvalidArgumentException('Некорректный id модуля для файлового хранилища.');
        }

        return new LocalFiles($this->root . '/modules/' . $id);
    }

    public function removeModule(string $id): void
    {
        $this->module($id)->deleteDirectory('');
    }
}
