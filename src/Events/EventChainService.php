<?php

declare(strict_types=1);

namespace MajorDoMo\Events;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use MajorDoMo\Database\DatabaseManager;

/**
 * Чтение целых цепочек журнала для списка и дерева действий.
 *
 * ID цепочки — ID её первой сохранившейся записи. Данные исполнения нужны,
 * чтобы не объявлять цепочку завершённой, пока её дочерние команды ждут очереди.
 *
 * @phpstan-type EventRow array{id:int,parent_id:int,type:string,status:string,source:string,object_title:string,property_title:string,handler:string,old_value:string,new_value:string,message:string,created_at:string}
 * @phpstan-type Chain array{id:int,root:EventRow,count:int,status:string,pending:int,partial:bool}
 *
 * 2026-10-28 — список цепочек, поиск по дочерним шагам и полное дерево журнала.
 */
final readonly class EventChainService
{
    public const PAGE_SIZE = 25;

    public function __construct(private DatabaseManager $database, private PropertyEventRepository $events)
    {
    }

    /** @return array{items:list<Chain>,more:bool} */
    public function listing(string $search = '', string $type = '', string $status = '', int $page = 1): array
    {
        if (!$this->events->hasTable()) {
            return ['items' => [], 'more' => false];
        }
        $query = $this->roots();
        $prefix = '';
        $bindings = [];
        if (trim($search) !== '' || $type !== '' || $status !== '') {
            $matches = $this->database->connection()->table('property_events')->whereNull('deleted_at');
            if ($type !== '') {
                $matches->where('type', $type);
            }
            if ($status !== '') {
                $matches->where('status', $status);
            }
            if (trim($search) !== '') {
                $matches->where(static function (Builder $match) use ($search): void {
                    foreach (['object_title', 'property_title', 'handler', 'source', 'message', 'old_value', 'new_value'] as $column) {
                        $match->orWhere($column, 'like', '%' . trim($search) . '%');
                    }
                    if (ctype_digit(ltrim(trim($search), '#'))) {
                        $match->orWhere('id', (int)ltrim(trim($search), '#'));
                    }
                });
            }
            $matches->select(['id', 'parent_id']);
            // Query Builder не выражает рекурсивный обход. CTE поднимается
            // от совпадений к корням; UNION исключает повторы и замкнутые ссылки.
            $eventTable = $this->database->connection()->getQueryGrammar()->wrapTable('property_events');
            $ancestorTable = $this->database->connection()->getQueryGrammar()->wrapTable('ancestors');
            $prefix = 'WITH RECURSIVE ' . $ancestorTable . ' AS (' . $matches->toSql()
                . ' UNION SELECT p.id, p.parent_id FROM ' . $eventTable . ' p'
                . ' INNER JOIN ' . $ancestorTable . ' a ON p.id = a.parent_id WHERE p.deleted_at IS NULL) ';
            $bindings = $matches->getBindings();
            $query->whereIn('event.id', static fn (Builder $ancestors) => $ancestors->select('id')->from('ancestors'));
        }
        $query->orderByDesc('event.id')->offset((max(1, $page) - 1) * self::PAGE_SIZE)->limit(self::PAGE_SIZE + 1);
        $roots = $this->database->connection()->select($prefix . $query->toSql(), [...$bindings, ...$query->getBindings()]);
        $more = count($roots) > self::PAGE_SIZE;
        $roots = array_slice($roots, 0, self::PAGE_SIZE);
        $rows = $this->descendants(array_map(static fn (object $root): int => (int)$root->id, $roots));
        $states = $this->executionStates(array_map(static fn (object $root): int => (int)$root->id, $roots));
        $items = [];
        foreach ($roots as $root) {
            $id = (int)$root->id;
            $items[] = $this->summary($this->row($root), $rows[$id] ?? [], $states[$id] ?? []);
        }

        return ['items' => $items, 'more' => $more];
    }

    /** @return array{chain:Chain,nodes:list<array<string,mixed>>}|null */
    public function detail(int $id): ?array
    {
        if ($id <= 0 || !$this->events->hasTable()) {
            return null;
        }
        $root = $this->roots()->where('event.id', $id)->first();
        if ($root === null) {
            return null;
        }
        $rows = $this->descendants([$id])[$id] ?? [];
        $states = $this->executionStates([$id]);
        $children = [];
        foreach ($rows as $row) {
            $children[$row['parent_id']][] = $row;
        }

        return [
            'chain' => $this->summary($this->row($root), $rows, $states[$id] ?? []),
            'nodes' => $this->nodes([$this->row($root)], $children, null, (new \DateTimeImmutable((string)$root->created_at))->getTimestamp()),
        ];
    }

    private function roots(): Builder
    {
        return $this->database->connection()->table('property_events as event')
            ->leftJoin('property_events as parent', static function (JoinClause $join): void {
                $join->on('parent.id', '=', 'event.parent_id')->whereNull('parent.deleted_at');
            })
            ->whereNull('event.deleted_at')
            ->where(static fn (Builder $root) => $root->where('event.parent_id', 0)->orWhereNull('parent.id'))
            ->select('event.*');
    }

    /** @param list<int> $ids
     * @return array<int,list<EventRow>>
     */
    private function descendants(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $start = $this->database->connection()->table('property_events')->whereIn('id', $ids)->whereNull('deleted_at')
            ->select(['id', 'parent_id'])->selectRaw('id AS root_id');
        // Загружаем потомков только выбранной страницы, без лимита на число
        // шагов: обрезка последних N записей разрывала бы длинные цепочки.
        $eventTable = $this->database->connection()->getQueryGrammar()->wrapTable('property_events');
        $sql = 'WITH RECURSIVE chain_rows AS (' . $start->toSql()
            . ' UNION ALL SELECT e.id, e.parent_id, c.root_id FROM ' . $eventTable . ' e'
            . ' INNER JOIN chain_rows c ON e.parent_id = c.id WHERE e.deleted_at IS NULL)'
            . ' SELECT e.*, c.root_id AS chain_root FROM ' . $eventTable . ' e'
            . ' INNER JOIN chain_rows c ON c.id = e.id ORDER BY e.id';
        $result = [];
        foreach ($this->database->connection()->select($sql, $start->getBindings()) as $row) {
            $result[(int)$row->chain_root][] = $this->row($row);
        }

        return $result;
    }

    /** @param list<int> $ids
     * @return array<int,list<string>>
     */
    private function executionStates(array $ids): array
    {
        if ($ids === [] || !$this->database->schema()->hasTable('property_executions')) {
            return [];
        }
        $links = $this->database->connection()->table('property_executions')->where(static function (Builder $query) use ($ids): void {
            $query->whereIn('event_id', $ids)->orWhereIn('handler_event_id', $ids);
        })->get(['root_id', 'event_id', 'handler_event_id']);
        $rootMap = [];
        foreach ($links as $link) {
            foreach ([(int)$link->event_id, (int)$link->handler_event_id] as $eventId) {
                if (in_array($eventId, $ids, true)) {
                    $rootMap[(int)$link->root_id][$eventId] = true;
                }
            }
        }
        $result = [];
        if ($rootMap !== []) {
            $steps = $this->database->connection()->table('property_executions')->whereIn('root_id', array_keys($rootMap))
                ->get(['root_id', 'status', 'failed_at']);
            foreach ($steps as $step) {
                foreach (array_keys($rootMap[(int)$step->root_id]) as $id) {
                    $result[$id][] = $step->failed_at !== null ? 'error' : (string)$step->status;
                }
            }
        }

        return $result;
    }

    /** @param EventRow $root
     * @param list<EventRow> $rows
     * @param list<string> $states
     * @return Chain
     */
    private function summary(array $root, array $rows, array $states): array
    {
        $statuses = [...array_column($rows, 'status'), ...$states];
        $status = 'ok';
        foreach (['error', 'missing', 'running', 'pending', 'stopped', 'skipped'] as $candidate) {
            if (in_array($candidate, $statuses, true)) {
                $status = match ($candidate) {
                    'missing' => 'error',
                    'pending' => 'running',
                    default => $candidate,
                };
                break;
            }
        }

        return [
            'id' => $root['id'], 'root' => $root, 'count' => count($rows), 'status' => $status,
            'pending' => count(array_filter($states, static fn (string $state): bool => in_array($state, ['pending', 'running'], true))),
            'partial' => $root['parent_id'] !== 0,
        ];
    }

    /** @param list<EventRow> $rows
     * @param array<int,list<EventRow>> $children
     * @return list<array<string,mixed>>
     */
    private function nodes(array $rows, array $children, ?string $parentSource = null, int $startedAt = 0): array
    {
        $nodes = [];
        foreach ($rows as $row) {
            $kind = $this->kind($row['type']);
            $node = [
                'kind' => $kind,
                'title' => $this->title($row),
                'entity' => ['type' => in_array($kind, ['property', 'method', 'module'], true) ? $kind : 'object', 'name' => $this->title($row)],
                'status' => match ($row['status']) {
                    'changed' => 'ok', 'missing' => 'error', 'stopped' => 'warning', default => $row['status'],
                },
                'time' => (new \DateTimeImmutable($row['created_at']))->format('H:i:s'),
                'actor' => $parentSource === $row['source'] ? '' : self::sourceLabel($row['source']),
                'note' => '',
                'detail' => $row['message'],
                'payload' => ['journal_id' => $row['id'], 'parent_id' => $row['parent_id'], 'source' => $row['source']],
                'children' => $this->nodes($children[$row['id']] ?? [], $children, $row['source'], $startedAt),
            ];
            $offset = (new \DateTimeImmutable($row['created_at']))->getTimestamp() - $startedAt;
            if ($offset > 0) {
                $node['offset'] = '+' . $offset . ' с';
            }
            if ($row['type'] === 'property.changed') {
                $node['before'] = $row['old_value'];
                $node['after'] = $row['new_value'];
            }
            if ($row['status'] === 'stopped') {
                $node['note'] = 'Цикл остановлен';
            }
            $nodes[] = $node;
        }

        return $nodes;
    }

    public static function sourceLabel(string $source): string
    {
        return match ($source) {
            '' => 'Источник не записан', 'sg' => 'PHP: sg()', 'panel', 'panel:objects', 'panel:pvalues' => 'Панель: изменение свойства',
            default => $source,
        };
    }

    private function kind(string $type): string
    {
        return match ($type) {
            'property.changed' => 'property', 'handler.method' => 'method', 'module.delivery' => 'module',
            default => 'event',
        };
    }

    /** @param EventRow $row */
    private function title(array $row): string
    {
        return match ($row['type']) {
            'property.changed' => $row['object_title'] . '.' . $row['property_title'],
            'handler.method' => $row['object_title'] . '.' . $row['handler'],
            default => $row['handler'] !== '' ? $row['handler'] : $row['type'],
        };
    }

    public static function formatTime(string $time): string
    {
        return (new \DateTimeImmutable($time))->format('d.m.Y H:i:s');
    }

    /** @return EventRow */
    private function row(object $row): array
    {
        return [
            'id' => (int)$row->id, 'parent_id' => (int)$row->parent_id,
            'type' => (string)$row->type, 'status' => (string)$row->status, 'source' => (string)$row->source,
            'object_title' => (string)$row->object_title, 'property_title' => (string)$row->property_title,
            'handler' => (string)$row->handler, 'old_value' => (string)($row->old_value ?? ''),
            'new_value' => (string)($row->new_value ?? ''), 'message' => (string)($row->message ?? ''),
            'created_at' => (string)$row->created_at,
        ];
    }
}
