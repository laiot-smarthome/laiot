<?php

declare(strict_types=1);

namespace MajorDoMo\Audit;

use MajorDoMo\Database\DatabaseManager;

/**
 * Журнал действий. Таблица `actions_log` — legacy alpha, поэтому её
 * uppercase-колонки читаются через адаптер, а не копируются в новую схему.
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
        if (!$connection->getSchemaBuilder()->hasTable('actions_log')) {
            return [];
        }

        $query = $connection->table('actions_log');
        if ($type !== '') {
            $query->where('ACTION_TYPE', $type);
        }
        if ($search !== '') {
            $query->where(static function ($query) use ($search): void {
                $query->where('TITLE', 'like', '%' . $search . '%')
                    ->orWhere('USER', 'like', '%' . $search . '%')
                    ->orWhere('IP', 'like', '%' . $search . '%');
            });
        }

        return $query->orderByDesc('ID')
            ->limit(max(1, min(500, $limit)))
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->ID,
                'added' => (string)$row->ADDED,
                'type' => (string)$row->ACTION_TYPE,
                'title' => (string)$row->TITLE,
                'user' => (string)$row->USER,
                'terminal' => (string)$row->TERMINAL,
                'ip' => (string)$row->IP,
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        $connection = $this->database->connection();
        if (!$connection->getSchemaBuilder()->hasTable('actions_log')) {
            return [];
        }

        return $connection->table('actions_log')
            ->distinct()
            ->orderBy('ACTION_TYPE')
            ->pluck('ACTION_TYPE')
            ->map(static fn (mixed $type): string => (string)$type)
            ->all();
    }

    public function delete(int $id): void
    {
        if ($id > 0) {
            $this->database->connection()->table('actions_log')->where('ID', $id)->delete();
        }
    }
}
