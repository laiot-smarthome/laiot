<?php

declare(strict_types=1);

namespace LaIOT\Services\WebSocket;

use LaIOT\Database\DatabaseManager;

/**
 * Outbox WebSocket-событий и снимок текущих значений.
 *
 * Outbox — не журнал событий и не очередь: строки нужны только WebSocket-
 * серверу, который читает их по возрастанию id. Снимок отдаётся вместе с
 * тикетом, чтобы страница получила актуальные значения до первого события.
 * Каналы свойств именуются «Объект.свойство», системные события идут отдельным
 * типом и общим каналом `system`.
 *
 * 2026-10-29 — имена свойств, системные события и снимок по именам.
 */
final readonly class WebSocketEventRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    public function maxId(): int
    {
        return (int)($this->database->connection()->table('websocket_events')->max('id') ?? 0);
    }

    /**
     * @return list<array{id:int,type:string,object_id:int,property_id:int,object_title:string,property_title:string,event_name:string,payload:string,old_value:string,value:string,source:string,created_at:string}>
     */
    public function after(int $id, int $limit): array
    {
        return $this->rows($id, $limit, false);
    }

    /**
     * Последние события после курсора: при большом разрыве важнее отдать
     * свежие значения, чем первые попавшиеся старые.
     *
     * @return list<array{id:int,type:string,object_id:int,property_id:int,object_title:string,property_title:string,event_name:string,payload:string,old_value:string,value:string,source:string,created_at:string}>
     */
    public function latestAfter(int $id, int $limit): array
    {
        return $this->rows($id, $limit, true);
    }

    /**
     * @param list<string> $channels
     * @return list<array{object:string,property:string,value:string}>
     */
    public function currentValues(array $channels): array
    {
        $wanted = [];
        foreach ($channels as $channel) {
            $pair = WebSocketChannels::parse($channel);
            if ($pair !== null) {
                $wanted[WebSocketChannels::key($pair[0], $pair[1])] = $pair;
            }
        }
        if ($wanted === []) {
            return [];
        }

        $values = [];
        foreach (array_chunk(array_values($wanted), 200) as $pairs) {
            $query = $this->database->connection()->table('property_values as pv')
                ->join('objects as o', 'o.id', '=', 'pv.object_id')
                ->join('properties as p', 'p.id', '=', 'pv.property_id')
                ->whereNull('pv.deleted_at')
                ->where(static function ($group) use ($pairs): void {
                    foreach ($pairs as $pair) {
                        $group->orWhere(static function ($item) use ($pair): void {
                            $item->where('o.title', $pair[0])
                                ->where('p.title', $pair[1]);
                        });
                    }
                });
            foreach ($query->get([
                'o.title as object_title',
                'p.title as property_title',
                'pv.value',
            ]) as $row) {
                $key = WebSocketChannels::key((string)$row->object_title, (string)$row->property_title);
                if (!isset($wanted[$key])) {
                    continue;
                }
                $values[$key] = [
                    'object' => (string)$row->object_title,
                    'property' => (string)$row->property_title,
                    'value' => (string)($row->value ?? ''),
                ];
            }
        }

        return array_values($values);
    }

    /**
     * Удаляет старые строки порциями, чтобы не держать долгую блокировку.
     */
    public function pruneBefore(string $cutoff, int $batch = 5000): int
    {
        $batch = max(100, min(50000, $batch));
        $total = 0;
        do {
            $ids = $this->database->connection()->table('websocket_events')
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batch)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int)$id)
                ->all();
            if ($ids === []) {
                break;
            }
            $total += (int)$this->database->connection()->table('websocket_events')
                ->whereIn('id', $ids)
                ->delete();
        } while (count($ids) === $batch);

        return $total;
    }

    /**
     * @return list<array{id:int,type:string,object_id:int,property_id:int,object_title:string,property_title:string,event_name:string,payload:string,old_value:string,value:string,source:string,created_at:string}>
     */
    private function rows(int $id, int $limit, bool $latest): array
    {
        $limit = max(1, min(5000, $limit));
        $query = $this->database->connection()->table('websocket_events')
            ->where('id', '>', max(0, $id))
            ->orderBy('id', $latest ? 'desc' : 'asc')
            ->limit($limit);
        $rows = $query->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'type' => (string)($row->type ?? 'property'),
                'object_id' => (int)($row->object_id ?? 0),
                'property_id' => (int)($row->property_id ?? 0),
                'object_title' => (string)($row->object_title ?? ''),
                'property_title' => (string)($row->property_title ?? ''),
                'event_name' => (string)($row->event_name ?? ''),
                'payload' => (string)($row->payload ?? ''),
                'old_value' => (string)($row->old_value ?? ''),
                'value' => (string)($row->value ?? ''),
                'source' => (string)($row->source ?? ''),
                'created_at' => (string)$row->created_at,
            ])
            ->all();

        return $latest ? array_reverse($rows) : $rows;
    }
}
