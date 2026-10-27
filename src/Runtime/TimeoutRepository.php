<?php

declare(strict_types=1);

namespace MajorDoMo\Runtime;

use MajorDoMo\Database\DatabaseManager;

/**
 * Отложенные задачи `setTimeOut` без автоматических циклов.
 *
 * Аренда защищает от двойного выполнения: таймер забирает один процесс,
 * подтверждение и снятие привязаны к токену. Просроченная аренда снова
 * доступна — так падение процесса не «замораживает» запись навсегда.
 *
 * 2026-10-27 — claim/markExecuted/release с токеном аренды.
 * 2026-10-27 — неуспешные таймеры откладываются, чтобы не блокировать остальные.
 */
final readonly class TimeoutRepository
{
    /** Сколько секунд запись считается занятой до повторного захвата. */
    private const CLAIM_SECONDS = 300;

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
     * Захватывает созревшие таймеры, выдавая каждому токен аренды.
     *
     * @return list<array{id:int,title:string,code:string,token:string}>
     */
    public function claim(int $limit = 50): array
    {
        $now = time();
        $nowString = date('Y-m-d H:i:s', $now);
        $cutoff = date('Y-m-d H:i:s', $now - self::CLAIM_SECONDS);
        $connection = $this->database->connection();

        $rows = $connection->table('runtime_timeouts')
            ->whereNull('executed_at')
            ->whereNull('deleted_at')
            ->where('run_at', '<=', $nowString)
            ->where(static function ($query) use ($cutoff): void {
                $query->whereNull('claimed_at')
                    ->orWhere('claimed_at', '<=', $cutoff);
            })
            ->orderBy('id')
            ->limit(max(1, min(100, $limit)))
            ->get(['id', 'title', 'code']);

        $claimed = [];
        foreach ($rows as $row) {
            $token = bin2hex(random_bytes(8));
            $updated = $connection->table('runtime_timeouts')
                ->where('id', (int)$row->id)
                ->whereNull('executed_at')
                ->whereNull('deleted_at')
                ->where(static function ($query) use ($cutoff): void {
                    $query->whereNull('claimed_at')
                        ->orWhere('claimed_at', '<=', $cutoff);
                })
                ->update([
                    'claimed_at' => $nowString,
                    'claim_token' => $token,
                    'updated_at' => $nowString,
                ]);
            if ($updated === 1) {
                $claimed[] = [
                    'id' => (int)$row->id,
                    'title' => (string)$row->title,
                    'code' => (string)$row->code,
                    'token' => $token,
                ];
            }
        }

        return $claimed;
    }

    /**
     * Чтение созревших записей без захвата — для диагностики.
     *
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

    public function markExecuted(int $id, string $token): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('runtime_timeouts')
            ->where('id', $id)
            ->where('claim_token', $token)
            ->update([
                'executed_at' => $now,
                'claimed_at' => null,
                'claim_token' => null,
                'updated_at' => $now,
            ]);
    }

    /** Откладывает повтор после ошибки на срок аренды. */
    public function release(int $id, string $token): void
    {
        $this->database->connection()->table('runtime_timeouts')
            ->where('id', $id)
            ->where('claim_token', $token)
            ->update([
                'claimed_at' => date('Y-m-d H:i:s'),
                'claim_token' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
