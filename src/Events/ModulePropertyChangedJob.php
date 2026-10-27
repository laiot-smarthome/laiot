<?php

declare(strict_types=1);

namespace MajorDoMo\Events;

use MajorDoMo\Queue\Job;

/**
 * Задание доставки изменения свойства одному модулю.
 *
 * Событие свойства больше не вызывает код модуля в своём процессе: оно
 * кладёт задание в очередь `module.<id>`, которую обслуживает worker этого
 * модуля. Медленный или упавший модуль задерживает только свою очередь.
 *
 * 2026-10-27 — задание создано для изоляции модульных очередей.
 */
final readonly class ModulePropertyChangedJob implements Job
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(private array $payload)
    {
    }

    public function name(): string
    {
        return 'module.property.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }
}
