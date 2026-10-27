<?php

declare(strict_types=1);

namespace MajorDoMo\Worker;

use RuntimeException;

/**
 * Блокировки процессов одной установки: удерживаются до закрытия файла.
 *
 * 2026-10-27 — долгий обработчик не освобождает полосу по истечении TTL кэша.
 */
final class ProcessLock
{
    /** @var list<resource> */
    private array $handles = [];

    public function __construct(private readonly string $directory)
    {
    }

    /** @param list<string> $keys */
    public function acquire(array $keys): bool
    {
        if ($this->handles !== []) {
            throw new RuntimeException('Блокировки процесса уже заняты.');
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Не удалось создать каталог блокировок worker.');
        }
        $keys = array_values(array_unique($keys));
        sort($keys);
        foreach ($keys as $key) {
            $handle = fopen($this->directory . '/' . hash('sha256', $key) . '.lock', 'c');
            if ($handle === false) {
                $this->release();
                throw new RuntimeException('Не удалось открыть блокировку worker.');
            }
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                fclose($handle);
                $this->release();

                return false;
            }
            $this->handles[] = $handle;
        }

        return true;
    }

    public function release(): void
    {
        foreach ($this->handles as $handle) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        $this->handles = [];
    }
}
