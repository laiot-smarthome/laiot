<?php

declare(strict_types=1);

namespace MajorDoMo\Scripts;

use MajorDoMo\Queue\Queue;

/**
 * Ставит запуск сценариев в очередь по системному событию.
 *
 * Работает в процессе генератора событий и только читает индекс сценариев:
 * пользовательский код выполняется позже в `core-worker queue`, поэтому
 * медленный или упавший сценарий не задерживает `system.minute`.
 *
 * 2026-10-27 — привязка сценария к системному событию.
 */
final readonly class ScriptEventDispatcher
{
    public const JOB = 'script.system.event';

    public function __construct(
        private ScriptRepository $scripts,
        private Queue $queue
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function dispatch(string $event, array $data): void
    {
        foreach ($this->scripts->byEvent($event) as $script) {
            $this->queue->push('default', self::JOB, [
                'id' => $script['id'],
                'title' => $script['title'],
                'event' => $event,
                'data' => $data,
            ]);
        }
    }
}
