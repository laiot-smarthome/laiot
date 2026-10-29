<?php

declare(strict_types=1);

namespace LaIOT\Services\Events;

use InvalidArgumentException;
use LaIOT\Services\Queue\Queue;

/**
 * Системные события: доставка модулям и ядровым наблюдателям.
 *
 * Имя события определяет владельца: ядро отправляет `system.*`
 * (`system.minute` и т.п.), модуль — `module.<id>.*`
 * (`module.mqtt.ready`). Одна форма на всех: получатель видит, кто источник.
 * Наблюдатель (`observe`) — синхронный получатель для кода ядра: он только
 * ставит задания в очередь и не выполняет пользовательский код в процессе
 * генератора событий.
 *
 * 2026-10-27 — события модулей переименованы в `module.<id>.*`.
 * 2026-10-27 — наблюдатель ядра: сценарии запускаются по системному событию.
 * 2026-10-27 — подписка и асинхронная доставка системных событий.
 */
final class SystemEvents
{
    /**
     * Допустимое имя события: событие ядра или событие модуля.
     */
    public const EVENT_PATTERN = '/^(?:system\.[a-z][a-z0-9_.-]{0,180}'
        . '|module\.[a-z][a-z0-9_]{1,63}\.[a-z][a-z0-9_.-]{0,180})$/D';

    /** @var array<string, array<string, list<callable>>> */
    private array $listeners = [];

    /** @var list<callable(string, array<string, mixed>): void> */
    private array $observers = [];

    public function __construct(private readonly Queue $queue)
    {
    }

    /**
     * Наблюдатель получает каждое событие в процессе, который его отправил.
     *
     * @param callable(string, array<string, mixed>): void $observer
     */
    public function observe(callable $observer): void
    {
        $this->observers[] = $observer;
    }

    public function subscribe(string $module, string $event, callable $listener): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $module)
            || preg_match(self::EVENT_PATTERN, $event) !== 1) {
            throw new InvalidArgumentException('Некорректное имя модуля или системного события.');
        }
        $this->listeners[$module][$event][] = $listener;
    }

    /** @param array<string, mixed> $data */
    public function emit(string $event, array $data = []): void
    {
        if (preg_match(self::EVENT_PATTERN, $event) !== 1) {
            throw new InvalidArgumentException(
                'Некорректное имя события: нужен system.<имя> или module.<модуль>.<имя>.'
            );
        }
        foreach ($this->observers as $observer) {
            $observer($event, $data);
        }
        foreach ($this->listeners as $module => $events) {
            if (isset($events[$event])) {
                $this->queue->push('module.' . $module, 'module.system.event', [
                    'module' => $module, 'event' => $event, 'data' => $data,
                ]);
            }
        }
    }

    /** @param array<string, mixed> $payload */
    public function deliver(array $payload): void
    {
        $module = $payload['module'] ?? null;
        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? null;
        if (!is_string($module) || !is_string($event) || !is_array($data)) {
            throw new InvalidArgumentException('Некорректное задание системного события.');
        }
        foreach ($this->listeners[$module][$event] ?? [] as $listener) {
            $listener($event, $data);
        }
    }
}
