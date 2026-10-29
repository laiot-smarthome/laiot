<?php

declare(strict_types=1);

namespace LaIOT\Services\Events;

use LaIOT\Services\Queue\Job;

/**
 * Задание «свойство изменилось» — единица асинхронного конвейера.
 *
 * Запись значения только сохраняет значение и кладёт это задание в очередь
 * `events` в той же транзакции. Всё остальное — журнал, OnChange, история и
 * доставка модулям — выполняет отдельный процесс `core-worker events`.
 *
 * Задание несёт снимок события: цепочку, глубину, родителя журнала, модуль-
 * автора (для гашения эха) и время фактической записи. Обработчик не
 * перечитывает эти данные из внешнего состояния.
 *
 * 2026-10-27 — задание создано для асинхронного конвейера свойств.
 */
final readonly class PropertyChangedJob implements Job
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(private array $payload)
    {
    }

    public function name(): string
    {
        return 'property.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }
}
