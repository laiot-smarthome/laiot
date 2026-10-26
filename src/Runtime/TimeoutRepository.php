<?php

declare(strict_types=1);

namespace MajorDoMo\Runtime;

use MajorDoMo\Database\DatabaseManager;

/**
 * Отложенные задачи `setTimeOut` без автоматических циклов.
 */
final readonly class TimeoutRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    public function set(string $title, string $code, int $delaySeconds): int
    {
        $now = date('Y-m-d H:i:s');

        return (int)$this->database->connection()->table('runtime_timeouts')->insertGetId([
            'title' => mb_substr(trim($title), 0, 191),
            'code' => $code,
            'run_at' => date('Y-m-d H:i:s', time() + max(0, $delaySeconds)),
            'executed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function clear(string $title): bool
    {
        $now = date('Y-m-d H:i:s');

        return $this->database->connection()->table('runtime_timeouts')
            ->where('title', $title)
            ->whereNull('executed_at')
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]) > 0;
    }

    /**
     * @return list<array{id:int,title:string,code:string}>
     */
    public function due(int $limit = 50): array
    {
        return $this->database->connection()->table('runtime_timeouts')
            ->whereNull('executed_at')
            ->whereNull('deleted_at')
            ->where('run_at', '<=', date('Y-m-d H:i:s'))
            ->orderBy('id')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'title' => (string)$row->title,
                'code' => (string)$row->code,
            ])
            ->all();
    }

    public function markExecuted(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('runtime_timeouts')
            ->where('id', $id)
            ->update(['executed_at' => $now, 'updated_at' => $now]);
    }
}
