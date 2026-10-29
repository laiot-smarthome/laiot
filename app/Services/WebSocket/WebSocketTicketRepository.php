<?php

declare(strict_types=1);

namespace LaIOT\Services\WebSocket;

use LaIOT\Database\DatabaseManager;
use Throwable;

/**
 * Тикеты WebSocket-подключения.
 *
 * В базе лежит только SHA-256 тикета. Тикет привязан к пользователю и
 * отпечатку его пароля, поэтому смена пароля или удаление пользователя
 * прекращают доступ; выход из панели отзывает тикеты явно.
 *
 * 2026-10-29 — выпуск, проверка, отзыв и очистка тикетов websocket.
 */
final readonly class WebSocketTicketRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    /**
     * @param list<string> $channels
     */
    public function issue(int $userId, string $stamp, array $channels, int $sinceId, int $ttlSeconds): string
    {
        $ticket = bin2hex(random_bytes(32));
        $now = time();
        $encoded = json_encode($channels, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException('Не удалось сохранить список свойств тикета.');
        }
        $this->database->connection()->table('websocket_tickets')->insert([
            'user_id' => $userId,
            'ticket_hash' => hash('sha256', $ticket),
            'stamp' => $stamp,
            'channels' => $encoded,
            'since_id' => max(0, $sinceId),
            'created_at' => date('Y-m-d H:i:s', $now),
            'expires_at' => date('Y-m-d H:i:s', $now + max(30, $ttlSeconds)),
            'revoked_at' => null,
            'last_used_at' => null,
        ]);

        return $ticket;
    }

    /**
     * @return array{user_id:int,username:string,name:string,channels:list<string>,since_id:int,expires_at:int}|null
     */
    public function resolve(string $ticket, int $maxChannels = 8192): ?array
    {
        $ticket = trim($ticket);
        if (preg_match('/^[a-f0-9]{64}$/D', $ticket) !== 1) {
            return null;
        }

        $row = $this->database->connection()->table('websocket_tickets as ticket')
            ->join('users as user', 'user.id', '=', 'ticket.user_id')
            ->where('ticket.ticket_hash', hash('sha256', $ticket))
            ->whereNull('ticket.revoked_at')
            ->where('ticket.expires_at', '>', date('Y-m-d H:i:s'))
            ->whereNull('user.deleted_at')
            ->where('user.is_admin', 1)
            ->first([
                'ticket.user_id',
                'ticket.stamp',
                'ticket.channels',
                'ticket.since_id',
                'ticket.expires_at',
                'user.username',
                'user.name',
                'user.password',
            ]);
        if ($row === null) {
            return null;
        }
        if (!hash_equals((string)$row->stamp, hash('sha256', (string)$row->password))) {
            return null;
        }
        $channels = WebSocketChannels::fromJson($row->channels, $maxChannels);
        if ($channels === []) {
            return null;
        }

        $this->database->connection()->table('websocket_tickets')
            ->where('ticket_hash', hash('sha256', $ticket))
            ->update(['last_used_at' => date('Y-m-d H:i:s')]);

        return [
            'user_id' => (int)$row->user_id,
            'username' => (string)$row->username,
            'name' => (string)$row->name,
            'channels' => $channels,
            'since_id' => max(0, (int)$row->since_id),
            'expires_at' => strtotime((string)$row->expires_at) ?: 0,
        ];
    }

    /**
     * Проверка для периодического обхода: тикет не отозван, пользователь
     * существует, остаётся администратором и не менял пароль.
     *
     * @param list<string> $hashes
     * @return array<string,int> hash тикета => id пользователя
     */
    public function validHashes(array $hashes): array
    {
        $result = [];
        foreach (array_chunk(array_values(array_unique($hashes)), 400) as $chunk) {
            $rows = $this->database->connection()->table('websocket_tickets as ticket')
                ->join('users as user', 'user.id', '=', 'ticket.user_id')
                ->whereIn('ticket.ticket_hash', $chunk)
                ->whereNull('ticket.revoked_at')
                ->whereNull('user.deleted_at')
                ->where('user.is_admin', 1)
                ->get(['ticket.ticket_hash', 'ticket.user_id', 'ticket.stamp', 'user.password']);
            foreach ($rows as $row) {
                if (!hash_equals((string)$row->stamp, hash('sha256', (string)$row->password))) {
                    continue;
                }
                $result[(string)$row->ticket_hash] = (int)$row->user_id;
            }
        }

        return $result;
    }

    /**
     * Выход из панели закрывает активные подключения этого пользователя.
     */
    public function revokeForUser(int $userId): int
    {
        try {
            return (int)$this->database->connection()->table('websocket_tickets')
                ->where('user_id', $userId)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => date('Y-m-d H:i:s'),
                ]);
        } catch (Throwable) {
            // Миграции websocket могут быть ещё не применены: выход не должен
            // из-за этого ломаться.
            return 0;
        }
    }

    public function pruneBefore(string $cutoff, int $batch = 2000): int
    {
        $batch = max(100, min(50000, $batch));
        $total = 0;
        do {
            $ids = $this->database->connection()->table('websocket_tickets')
                ->where('expires_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batch)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int)$id)
                ->all();
            if ($ids === []) {
                break;
            }
            $total += (int)$this->database->connection()->table('websocket_tickets')
                ->whereIn('id', $ids)
                ->delete();
        } while (count($ids) === $batch);

        return $total;
    }
}
