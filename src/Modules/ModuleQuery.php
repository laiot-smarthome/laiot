<?php

declare(strict_types=1);

namespace MajorDoMo\Modules;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Ограниченный query builder модуля.
 *
 * Здесь только операции над уже проверенной своей таблицей. Нет join(),
 * raw(), whereRaw(), selectRaw() и доступа к произвольному SQL.
 *
 * 2026-10-27 — whereGroup/orWhere для поиска без смешивания OR с фильтрами.
 * 2026-10-27 — insert принимает пакет строк: история пишется одной вставкой.
 */
final class ModuleQuery
{
    public function __construct(private readonly Builder $query)
    {
    }

    public function where(string $column, mixed $operator = null, mixed $value = null): self
    {
        $this->query->where($column, $operator, $value);

        return $this;
    }

    /**
     * Условия в скобках: поиск по нескольким полям не должен смешиваться
     * с фильтрами, которые добавлены до или после него.
     *
     * @param callable(self):void $callback
     */
    public function whereGroup(callable $callback): self
    {
        $this->query->where(static function ($query) use ($callback): void {
            $callback(new self($query));
        });

        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        $this->query->orWhere($column, $operator, $value);

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->query->whereNull($column);

        return $this;
    }

    /**
     * @param array<int, mixed> $values
     */
    public function whereIn(string $column, array $values): self
    {
        $this->query->whereIn($column, $values);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    public function orderByDesc(string $column): self
    {
        $this->query->orderByDesc($column);

        return $this;
    }

    public function limit(int $value): self
    {
        $this->query->limit($value);

        return $this;
    }

    public function offset(int $value): self
    {
        $this->query->offset($value);

        return $this;
    }

    /**
     * @return Collection<int, mixed>
     */
    public function get(): Collection
    {
        return $this->query->get();
    }

    public function first(): mixed
    {
        return $this->query->first();
    }

    public function value(string $column): mixed
    {
        return $this->query->value($column);
    }

    /**
     * @return Collection<int, mixed>
     */
    public function pluck(string $column): Collection
    {
        return $this->query->pluck($column);
    }

    public function count(): int
    {
        return (int)$this->query->count();
    }

    public function exists(): bool
    {
        return $this->query->exists();
    }

    /**
     * Одна строка (`column => value`) или список строк: пакетная вставка
     * истории не должна превращаться в N отдельных запросов.
     *
     * @param array<array-key, mixed> $values
     */
    public function insert(array $values): bool
    {
        return $this->query->insert($values);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function insertGetId(array $values): int
    {
        return (int)$this->query->insertGetId($values);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function update(array $values): int
    {
        return $this->query->update($values);
    }

    public function delete(): int
    {
        return $this->query->delete();
    }
}
