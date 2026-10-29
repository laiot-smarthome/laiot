<?php

declare(strict_types=1);

namespace LaIOT\Support;

/**
 * Режим обслуживания: файл-флаг в var, без legacy-настроек.
 */
final readonly class MaintenanceMode
{
    public function __construct(private string $root)
    {
    }

    public function enabled(): bool
    {
        return is_file($this->flagPath());
    }

    public function enable(): void
    {
        $path = $this->flagPath();
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
            throw new \RuntimeException('Не удалось создать каталог для maintenance-флага.');
        }
        file_put_contents($path, date('Y-m-d H:i:s'));
    }

    public function disable(): void
    {
        if (is_file($this->flagPath())) {
            unlink($this->flagPath());
        }
    }

    private function flagPath(): string
    {
        return $this->root . '/var/maintenance.flag';
    }
}
