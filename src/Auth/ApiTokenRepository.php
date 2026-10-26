<?php

declare(strict_types=1);

namespace MajorDoMo\Auth;

use MajorDoMo\Database\DatabaseManager;
use RuntimeException;

/**
 * API-токены: в БД хранится только SHA-256, открытый токен показывается один
 * раз при выпуске.
 */
final readonly class ApiTokenRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    public function issue(int $userId, string $name, ?int $ttlSeconds = null): string
    {
        if ($userId < 1) {
            throw new RuntimeException('Некорректный пользователь токена.');
        }
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 191) {
            throw new RuntimeException('Некорректное имя API-токена.');
        }

        $token = bin2hex(random_bytes(32));
        $now = time();
        $this->database->connection()->table('api_tokens')->insert([
            'user_id' => $userId,
            'name' => $name,
            'token_hash' => hash('sha256', $token),
            'created_at' => date('Y-m-d H:i:s', $now),
            'updated_at' => date('Y-m-d H:i:s', $now),
            'expires_at' => $ttlSeconds !== null && $ttlSeconds > 0
                ? date('Y-m-d H:i:s', $now + $ttlSeconds)
                : null,
            'last_used_at' => null,
            'revoked_at' => null,
            'deleted_at' => null,
        ]);

        return $token;
    }

    /**
     * @return list<array{id:int,user_id:int,user_name:string,name:string,created_at:string,last_used_at:string,expires_at:string,revoked_at:string}>
     */
    public function all(): array
    {
        return $this->database->connection()->table('api_tokens')
            ->leftJoin('users', 'users.id', '=', 'api_tokens.user_id')
            ->whereNull('api_tokens.deleted_at')
            ->orderByDesc('api_tokens.id')
            ->get([
                'api_tokens.*',
                'users.name as user_name',
            ])
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'user_id' => (int)$row->user_id,
                'user_name' => (string)($row->user_name ?? ''),
                'name' => (string)$row->name,
                'created_at' => (string)$row->created_at,
                'last_used_at' => (string)($row->last_used_at ?? ''),
                'expires_at' => (string)($row->expires_at ?? ''),
                'revoked_at' => (string)($row->revoked_at ?? ''),
            ])
            ->all();
    }

    public function userId(string $token): ?int
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 200) {
            return null;
        }

        $row = $this->database->connection()->table('api_tokens')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('deleted_at')
            ->whereNull('revoked_at')
            ->where(static function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
            })
            ->first();

        if ($row === null) {
            return null;
        }

        $this->database->connection()->table('api_tokens')
            ->where('id', (int)$row->id)
            ->update([
                'last_used_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return (int)$row->user_id;
    }

    public function revoke(int $id): bool
    {
        return $this->database->connection()->table('api_tokens')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }
}
