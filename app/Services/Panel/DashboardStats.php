<?php

declare(strict_types=1);

namespace LaIOT\Services\Panel;

use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Queue\Queue;
use LaIOT\Support\Clock;
use Throwable;

/**
 * Данные обзора админки.
 *
 * Читает только ядровые таблицы и контракт `Queue`. Каждый запрос защищён
 * проверкой наличия таблицы: обзор обязан открываться на свежей установке,
 * где миграции ещё не применялись, и не превращаться в 500.
 *
 * 2026-10-26 — сервис выделен из контроллера, чтобы обзор и статус считали
 * показатели одинаково.
 */
final readonly class DashboardStats
{
    public function __construct(
        private DatabaseManager $database,
        private Queue $queue,
        private Clock $clock
    ) {
    }

    /**
     * Счётчики сущностей дома и журналов за сутки.
     *
     * @return array{
     *     objects:int, classes:int, properties:int, methods:int, scripts:int,
     *     locations:int, users:int, events24:int, errors24:int, actions24:int
     * }
     */
    public function counts(): array
    {
        $dayAgo = $this->clock->now()->subDay()->format('Y-m-d H:i:s');

        return [
            'objects' => $this->count('objects'),
            'classes' => $this->count('classes'),
            'properties' => $this->count('properties'),
            'methods' => $this->count('methods'),
            'scripts' => $this->count('scripts'),
            'locations' => $this->count('locations'),
            'users' => $this->count('users'),
            'events24' => $this->countSince('property_events', $dayAgo),
            'errors24' => $this->countSince('system_errors', $dayAgo),
            'actions24' => $this->countSince('action_log', $dayAgo),
        ];
    }

    /**
     * @return array{pending:int,reserved:int,failed:int}
     */
    public function queue(): array
    {
        try {
            return $this->queue->stats('default');
        } catch (Throwable) {
            return ['pending' => 0, 'reserved' => 0, 'failed' => 0];
        }
    }

    /**
     * Последние изменения свойств: журнал цепочки `property_events`.
     *
     * @return list<array{id:int,object:string,property:string,value:string,old:string,status:string,type:string,at:string}>
     */
    public function recentEvents(int $limit = 8): array
    {
        if (!$this->hasTable('property_events')) {
            return [];
        }

        try {
            $rows = $this->database->connection()->table('property_events')
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->limit(max(1, min(50, $limit)))
                ->get();
        } catch (Throwable) {
            return [];
        }

        $events = [];
        foreach ($rows as $row) {
            $events[] = [
                'id' => (int)$row->id,
                'object' => (string)($row->object_title ?? ''),
                'property' => (string)($row->property_title ?? ''),
                'value' => $this->shorten((string)($row->new_value ?? ''), 60),
                'old' => $this->shorten((string)($row->old_value ?? ''), 40),
                'status' => (string)($row->status ?? ''),
                'type' => (string)($row->type ?? ''),
                'at' => (string)($row->created_at ?? ''),
            ];
        }

        return $events;
    }

    /**
     * Последние ошибки ядра.
     *
     * @return list<array{id:int,level:string,message:string,at:string,request_id:string}>
     */
    public function recentErrors(int $limit = 5): array
    {
        if (!$this->hasTable('system_errors')) {
            return [];
        }

        try {
            $rows = $this->database->connection()->table('system_errors')
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->limit(max(1, min(20, $limit)))
                ->get();
        } catch (Throwable) {
            return [];
        }

        $errors = [];
        foreach ($rows as $row) {
            $errors[] = [
                'id' => (int)$row->id,
                'level' => (string)($row->level ?? 'error'),
                'message' => $this->shorten((string)($row->message ?? ''), 160),
                'at' => (string)($row->created_at ?? ''),
                'request_id' => (string)($row->request_id ?? ''),
            ];
        }

        return $errors;
    }

    /**
     * Последние действия администраторов.
     *
     * @return list<array{id:int,type:string,title:string,user:string,at:string}>
     */
    public function recentActions(int $limit = 6): array
    {
        if (!$this->hasTable('action_log')) {
            return [];
        }

        try {
            $rows = $this->database->connection()->table('action_log')
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->limit(max(1, min(20, $limit)))
                ->get();
        } catch (Throwable) {
            return [];
        }

        $actions = [];
        foreach ($rows as $row) {
            $actions[] = [
                'id' => (int)$row->id,
                'type' => (string)($row->type ?? ''),
                'title' => $this->shorten((string)($row->title ?? ''), 80),
                'user' => (string)($row->user_name ?? ''),
                'at' => (string)($row->created_at ?? ''),
            ];
        }

        return $actions;
    }

    private function count(string $table): int
    {
        if (!$this->hasTable($table)) {
            return 0;
        }

        try {
            return (int)$this->database->connection()->table($table)->whereNull('deleted_at')->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countSince(string $table, string $from): int
    {
        if (!$this->hasTable($table)) {
            return 0;
        }

        try {
            return (int)$this->database->connection()->table($table)
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $from)
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function hasTable(string $table): bool
    {
        try {
            return $this->database->schema()->hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    private function shorten(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit - 1) . '…' : $value;
    }
}
