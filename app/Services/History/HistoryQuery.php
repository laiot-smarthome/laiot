<?php

declare(strict_types=1);

namespace LaIOT\Services\History;

use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use LaIOT\Database\DatabaseManager;

/**
 * Построитель выборки по истории одного свойства.
 *
 * История — это временной ряд, и типичный вопрос звучит как «средняя
 * температура за сутки», «сколько записей за неделю», «последнее значение».
 * Поэтому выборка собирается цепочкой: период → лимит → значения или
 * агрегат. Агрегаты считает SQL по индексу
 * `(object_id, property_id, created_at)`, а не PHP по всем строкам.
 *
 * Значения в истории хранятся строками; для min/max/avg/sum они приводятся
 * к числу. Пустые строки в числовые агрегаты не входят, но в `count()`
 * входят — это число записей, а не число чисел.
 *
 * 2026-10-27 — построитель создан вместо набора разрозненных обёрток.
 */
final class HistoryQuery
{
    private const MAX_LIMIT = 5000;

    private ?string $from = null;

    private ?string $to = null;

    private int $limit = 500;

    private bool $oldestFirst = false;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly int $objectId,
        private readonly int $propertyId
    ) {
    }

    /**
     * Начало периода: дата, `DateTimeInterface` или относительная строка
     * (`-1 day`, `today`, `2026-10-01 00:00:00`).
     */
    public function from(DateTimeInterface|string|null $from): self
    {
        $this->from = $this->moment($from, 'from');

        return $this;
    }

    /**
     * Конец периода; включительно.
     */
    public function to(DateTimeInterface|string|null $to): self
    {
        $this->to = $this->moment($to, 'to');

        return $this;
    }

    /** Период одной строкой: `from` и `to` задаются явно. */
    public function period(DateTimeInterface|string|null $from, DateTimeInterface|string|null $to = null): self
    {
        return $this->from($from)->to($to);
    }

    public function last(int $limit): self
    {
        $this->limit = max(1, min(self::MAX_LIMIT, $limit));

        return $this;
    }

    public function oldestFirst(): self
    {
        $this->oldestFirst = true;

        return $this;
    }

    public function newestFirst(): self
    {
        $this->oldestFirst = false;

        return $this;
    }

    /**
     * @return list<array{value:string,created_at:string}>
     */
    public function values(): array
    {
        return $this->base()
            ->orderBy('id', $this->oldestFirst ? 'asc' : 'desc')
            ->limit($this->limit)
            ->get(['value', 'created_at'])
            ->map(static fn (object $row): array => [
                'value' => (string)($row->value ?? ''),
                'created_at' => (string)$row->created_at,
            ])
            ->all();
    }

    public function count(): int
    {
        return (int)$this->base()->count();
    }

    /** Последнее сохранённое значение в выбранном периоде. */
    public function lastValue(): ?string
    {
        $value = $this->base()->orderByDesc('id')->value('value');

        return $value === null ? null : (string)$value;
    }

    public function avg(): ?float
    {
        return $this->aggregate()->avg;
    }

    public function min(): ?float
    {
        return $this->aggregate()->min;
    }

    public function max(): ?float
    {
        return $this->aggregate()->max;
    }

    public function sum(): ?float
    {
        return $this->aggregate()->sum;
    }

    /**
     * Все агрегаты одним запросом.
     *
     * @return array{count:int,min:?float,max:?float,avg:?float,sum:?float,last:?string}
     */
    public function stats(): array
    {
        $aggregate = $this->aggregate();

        return [
            'count' => $aggregate->count,
            'min' => $aggregate->min,
            'max' => $aggregate->max,
            'avg' => $aggregate->avg,
            'sum' => $aggregate->sum,
            'last' => $this->lastValue(),
        ];
    }

    /**
     * Один запрос для числовых агрегатов.
     */
    private function aggregate(): HistoryAggregate
    {
        $numeric = 'CAST(value AS DECIMAL(20,6))';
        $row = $this->base()
            ->selectRaw(
                'COUNT(*) AS total, '
                . 'MIN(CASE WHEN value <> \'\' THEN ' . $numeric . ' END) AS min_value, '
                . 'MAX(CASE WHEN value <> \'\' THEN ' . $numeric . ' END) AS max_value, '
                . 'AVG(CASE WHEN value <> \'\' THEN ' . $numeric . ' END) AS avg_value, '
                . 'SUM(CASE WHEN value <> \'\' THEN ' . $numeric . ' END) AS sum_value'
            )
            ->first();

        if ($row === null) {
            return new HistoryAggregate(0, null, null, null, null);
        }

        return new HistoryAggregate(
            (int)$row->total,
            self::number($row->min_value),
            self::number($row->max_value),
            self::number($row->avg_value),
            self::number($row->sum_value)
        );
    }

    private function base(): Builder
    {
        $query = $this->database->connection()->table('property_history')
            ->where('object_id', $this->objectId)
            ->where('property_id', $this->propertyId)
            ->whereNull('deleted_at');

        if ($this->from !== null) {
            $query->where('created_at', '>=', $this->from);
        }
        if ($this->to !== null) {
            $query->where('created_at', '<=', $this->to);
        }

        return $query;
    }

    private function moment(DateTimeInterface|string|null $value, string $argument): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new InvalidArgumentException('Некорректная дата периода: ' . $argument . '=' . $value);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private static function number(mixed $value): ?float
    {
        return $value === null ? null : (float)$value;
    }
}
