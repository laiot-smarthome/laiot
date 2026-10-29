<?php

declare(strict_types=1);

namespace LaIOT\Services\History;

/**
 * Результат одного агрегатного запроса истории.
 *
 * Отдельный объект, чтобы `stats()` и отдельные `avg()`/`min()` не гоняли
 * один и тот же SQL повторно.
 *
 * 2026-10-27 — объект создан вместе с HistoryQuery.
 *
 * @internal
 */
final readonly class HistoryAggregate
{
    public function __construct(
        public int $count,
        public ?float $min,
        public ?float $max,
        public ?float $avg,
        public ?float $sum
    ) {
    }
}
