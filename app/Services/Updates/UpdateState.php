<?php

declare(strict_types=1);

namespace LaIOT\Services\Updates;

use RuntimeException;

/**
 * Журнал фаз переживает перезагрузку; отсутствие flock отличает прерывание от работающего процесса.
 * 2026-10-29 — операция, ключ аварийного доступа и явный выбор восстановления.
 */
final readonly class UpdateState
{
    public function __construct(public string $directory)
    {
    }

    /** @return array<string, mixed> */
    public function read(): array
    {
        $state = UpdateFiles::read($this->directory . '/operation.json');
        if (in_array($state['status'] ?? '', ['succeeded', 'rolled_back'], true)
            && is_file($this->directory . '/maintenance.flag') && !$this->locked()) {
            $state['status'] = 'interrupted';
        }
        if (in_array($state['status'] ?? '', ['running', 'queued'], true) && !$this->locked()
            && time() - (int)($state['heartbeat'] ?? 0) > 15) {
            $state['status'] = 'interrupted';
        }
        return $state;
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return UpdateFiles::read($this->directory . '/operation.json');
    }

    /** @param array<string, mixed> $changes */
    public function save(array $changes): void
    {
        UpdateFiles::json($this->directory . '/operation.json', array_replace($this->raw(), $changes, ['heartbeat' => time()]));
    }

    public function busy(): bool
    {
        return in_array($this->read()['status'] ?? '', ['queued', 'running', 'interrupted', 'failed'], true);
    }

    public function locked(): bool
    {
        if (!is_file($this->directory . '/run.lock')) {
            return false;
        }
        $lock = fopen($this->directory . '/run.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Недоступна блокировка обновления.');
        }
        $locked = !flock($lock, LOCK_EX | LOCK_NB);
        fclose($lock);
        return $locked;
    }

    /** @return resource */
    public function lock(string $name)
    {
        UpdateFiles::directory($this->directory);
        $lock = fopen($this->directory . '/' . $name . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Другая операция обновления уже выполняется.');
        }
        return $lock;
    }

    public function phase(string $phase, string $message): void
    {
        $events = (array)($this->raw()['events'] ?? []);
        $events[] = ['phase' => $phase, 'message' => $message, 'at' => gmdate('c')];
        $this->save(['phase' => $phase, 'message' => $message, 'events' => array_slice($events, -80)]);
    }
}
