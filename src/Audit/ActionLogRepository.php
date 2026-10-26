<?php

declare(strict_types=1);

namespace MajorDoMo\Audit;

use MajorDoMo\Database\DatabaseManager;

/**
 * Журнал действий MyDoMo.
 *
 * Новая таблица `action_log`, lowercase, timestamps и soft deletes.
 */
final readonly class ActionLogRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    /**
     * @return list<array{id:int,added:string,type:string,title:string,user:string,terminal:string,ip:string}>
     */
    public function latest(int $limit = 100, string $type = '', string $search = ''): array
    {
        $connection = $this->database->connection();
        if (!$connection->getSchemaBuilder()->hasTable('action_log')) {
            return [];
        }

        $query = $connection->table('action_log')->whereNull('deleted_at');
        if ($type !== '') {
            $query->where('type', $type);
        }
        if ($search !== '') {
            $query->where(static function ($query) use ($search): void {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('user_name', 'like', '%' . $search . '%')
                    ->orWhere('ip', 'like', '%' . $search . '%');
            });
        }

        return $query->orderByDesc('id')
            ->limit(max(1, min(500, $limit)))
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'added' => (string)$row->created_at,
                'type' => (string)$row->type,
                'title' => (string)$row->title,
                'user' => (string)$row->user_name,
                'terminal' => (string)$row->terminal,
                'ip' => (string)$row->ip,
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        $connection = $this->database->connection();
        if (!$connection->getSchemaBuilder()->hasTable('action_log')) {
            return [];
        }

        return $connection->table('action_log')
            ->whereNull('deleted_at')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->map(static fn (mixed $type): string => (string)$type)
            ->all();
    }

    public function delete(int $id): void
    {
        if ($id > 0) {
            $now = date('Y-m-d H:i:s');
            $this->database->connection()->table('action_log')
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now, 'updated_at' => $now]);
        }
    }
}
