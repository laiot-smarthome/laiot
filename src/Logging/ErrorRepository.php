<?php

declare(strict_types=1);

namespace MajorDoMo\Logging;

use MajorDoMo\Database\DatabaseManager;
use Throwable;

/**
 * Ошибки MyDoMo в БД для админки.
 *
 * Запись никогда не должна маскировать исходную ошибку: любые сбои
 * логирования проглатываются.
 */
final readonly class ErrorRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function record(string $level, string $message, array $context, string $requestId): void
    {
        if (!$this->database->isConfigured()) {
            return;
        }

        try {
            $now = date('Y-m-d H:i:s');
            $this->database->connection()->table('system_errors')->insert([
                'level' => mb_substr($level, 0, 16),
                'message' => mb_substr($message, 0, 4000),
                'context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'request_id' => $requestId,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        } catch (Throwable) {
            // Ошибка БД не должна ломать ответ на исходную ошибку.
        }
    }

    /**
     * @return list<array{id:int,level:string,message:string,request_id:string,created_at:string}>
     */
    public function latest(int $limit = 100, string $level = ''): array
    {
        $connection = $this->database->connection();
        if (!$connection->getSchemaBuilder()->hasTable('system_errors')) {
            return [];
        }

        $query = $connection->table('system_errors')->whereNull('deleted_at');
        if ($level !== '') {
            $query->where('level', $level);
        }

        return $query->orderByDesc('id')
            ->limit(max(1, min(500, $limit)))
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'level' => (string)$row->level,
                'message' => (string)$row->message,
                'request_id' => (string)$row->request_id,
                'created_at' => (string)$row->created_at,
            ])
            ->all();
    }

    public function delete(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('system_errors')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    /**
     * Удаление всех записей одной группы (level + message).
     *
     * Группировка в UI не хранится отдельной таблицей: одинаковые сообщения
     * считаются одной ошибкой, поэтому чистить журнал удобнее группой.
     */
    public function deleteGroup(string $level, string $message): int
    {
        $now = date('Y-m-d H:i:s');

        return (int)$this->database->connection()->table('system_errors')
            ->where('level', $level)
            ->where('message', $message)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }
}
