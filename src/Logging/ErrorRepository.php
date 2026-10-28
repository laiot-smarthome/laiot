<?php

declare(strict_types=1);

namespace MajorDoMo\Logging;

use MajorDoMo\Database\DatabaseManager;
use Throwable;

/**
 * Ошибки MyDoMo в БД для админки.
 *
 * Каждая запись получает отдельный файл (`log_file`) с контекстом и trace:
 * админка показывает журнал, а разбор конкретной ошибки не тонет в общем
 * потоке. Файл удаляется вместе с записью или группой.
 *
 * Запись никогда не должна маскировать исходную ошибку: любые сбои
 * логирования проглатываются.
 *
 * 2026-10-27 — отдельный файл на ошибку и удаление файлов вместе с записью.
 * 2026-10-27 — find() и purgeBefore() для просмотра и retention.
 */
final class ErrorRepository
{
    /** Наличие log_file проверяется один раз: старые тестовые схемы без колонки. */
    private ?bool $hasLogFile = null;

    /** Наличие таблицы проверяется один раз на процесс. */
    private ?bool $hasTable = null;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly ErrorFileWriter $files
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function record(string $level, string $message, array $context, string $requestId): void
    {
        if (!$this->database->isConfigured()) {
            return;
        }

        $exception = $context['exception'] ?? null;
        unset($context['exception']);

        try {
            $now = date('Y-m-d H:i:s');
            $id = (int)$this->database->connection()->table('system_errors')->insertGetId([
                'level' => mb_substr($level, 0, 16),
                'message' => mb_substr($message, 0, 4000),
                'context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'request_id' => $requestId,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
            if ($id <= 0) {
                return;
            }
            if (!$this->hasLogFile()) {
                return;
            }

            $file = $this->files->write(
                $id,
                $level,
                $message,
                $context,
                $requestId,
                $exception instanceof Throwable ? $exception : null
            );
            if ($file !== null) {
                $this->database->connection()->table('system_errors')
                    ->where('id', $id)
                    ->update(['log_file' => $file]);
            }
        } catch (Throwable) {
            // Ошибка БД не должна ломать ответ на исходную ошибку.
        }
    }

    /**
     * @return list<array{id:int,level:string,message:string,request_id:string,created_at:string,log_file:string}>
     */
    public function latest(int $limit = 100, string $level = ''): array
    {
        $connection = $this->database->connection();
        if (!$this->available()) {
            return [];
        }

        $query = $connection->table('system_errors')->whereNull('deleted_at');
        if ($level !== '') {
            $query->where('level', $level);
        }
        $hasFile = $this->hasLogFile();

        return $query->orderByDesc('id')
            ->limit(max(1, min(500, $limit)))
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'level' => (string)$row->level,
                'message' => (string)$row->message,
                'request_id' => (string)$row->request_id,
                'created_at' => (string)$row->created_at,
                'log_file' => $hasFile ? (string)($row->log_file ?? '') : '',
            ])
            ->all();
    }

    public function delete(int $id): void
    {
        $connection = $this->database->connection();
        $file = $this->hasLogFile() ? $connection->table('system_errors')->where('id', $id)->value('log_file') : null;
        $now = date('Y-m-d H:i:s');
        $connection->table('system_errors')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
        $this->files->delete(is_string($file) ? $file : null);
    }

    /**
     * Удаление всех записей одной группы (level + message).
     *
     * Группировка в UI не хранится отдельной таблицей: одинаковые сообщения
     * считаются одной ошибкой, поэтому чистить журнал удобнее группой.
     */
    public function deleteGroup(string $level, string $message): int
    {
        $connection = $this->database->connection();
        $files = $this->hasLogFile()
            ? $connection->table('system_errors')
                ->where('level', $level)
                ->where('message', $message)
                ->whereNull('deleted_at')
                ->pluck('log_file')
            : collect();
        $now = date('Y-m-d H:i:s');

        $deleted = (int)$connection->table('system_errors')
            ->where('level', $level)
            ->where('message', $message)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);

        foreach ($files as $file) {
            $this->files->delete(is_string($file) ? $file : null);
        }

        return $deleted;
    }

    /**
     * Одна запись для страницы файла ошибки.
     *
     * `context` возвращается сырым JSON: разбор и показ делает админка, а
     * репозиторий остаётся хранилищем. Старые схемы без колонки дают пустую
     * строку.
     *
     * @return array{id:int,level:string,message:string,request_id:string,created_at:string,log_file:string,context:string}|null
     */
    public function find(int $id): ?array
    {
        if ($id <= 0 || !$this->available()) {
            return null;
        }
        $row = $this->database->connection()->table('system_errors')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int)$row->id,
            'level' => (string)$row->level,
            'message' => (string)$row->message,
            'request_id' => (string)$row->request_id,
            'created_at' => (string)$row->created_at,
            'log_file' => $this->hasLogFile() ? (string)($row->log_file ?? '') : '',
            'context' => (string)($row->context ?? ''),
        ];
    }

    public function countBefore(string $cutoff): int
    {
        if (!$this->available()) {
            return 0;
        }

        return (int)$this->database->connection()->table('system_errors')
            ->where('created_at', '<', $cutoff)
            ->count();
    }

    /**
     * Точное число повторов группы и период, без ограничения выборки списка.
     *
     * @return array{count:int,first_at:string,last_at:string}
     */
    public function groupStats(string $level, string $message): array
    {
        if (!$this->available()) {
            return ['count' => 0, 'first_at' => '', 'last_at' => ''];
        }
        $row = $this->database->connection()->table('system_errors')
            ->where('level', $level)
            ->where('message', $message)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) AS aggregate, MIN(created_at) AS first_at, MAX(created_at) AS last_at')
            ->first();

        return [
            'count' => (int)($row->aggregate ?? 0),
            'first_at' => (string)($row->first_at ?? ''),
            'last_at' => (string)($row->last_at ?? ''),
        ];
    }

    /**
     * Физически удаляет старые ошибки вместе с их файлами.
     *
     * В отличие от `delete()`, retention не оставляет soft-deleted строку:
     * цель — вернуть место, а не скрыть запись. Порции ограничены, чтобы
     * очистка не держала таблицу заблокированной на минуты.
     */
    public function purgeBefore(string $cutoff, int $batch = 500): int
    {
        if (!$this->available()) {
            return 0;
        }
        $batch = max(1, min(5000, $batch));
        $connection = $this->database->connection();
        $hasFile = $this->hasLogFile();
        $total = 0;

        do {
            $query = $connection->table('system_errors')
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batch);
            $rows = $hasFile ? $query->get(['id', 'log_file']) : $query->get(['id']);
            $ids = [];
            foreach ($rows as $row) {
                $ids[] = (int)$row->id;
                if ($hasFile) {
                    $this->files->delete((string)($row->log_file ?? ''));
                }
            }
            if ($ids === []) {
                break;
            }
            $connection->table('system_errors')->whereIn('id', $ids)->delete();
            $total += count($ids);
        } while (count($ids) === $batch);

        return $total;
    }

    /**
     * Старые тестовые схемы могут не иметь колонку log_file; в этом случае
     * отдельный файл не пишется, а журнал продолжает работать.
     */
    private function hasLogFile(): bool
    {
        if ($this->hasLogFile !== null) {
            return $this->hasLogFile;
        }

        try {
            $this->hasLogFile = $this->database->connection()->getSchemaBuilder()
                ->hasColumn('system_errors', 'log_file');
        } catch (Throwable) {
            $this->hasLogFile = false;
        }

        return $this->hasLogFile;
    }

    private function available(): bool
    {
        if ($this->hasTable !== null) {
            return $this->hasTable;
        }

        try {
            $this->hasTable = $this->database->connection()->getSchemaBuilder()->hasTable('system_errors');
        } catch (Throwable) {
            $this->hasTable = false;
        }

        return $this->hasTable;
    }
}
