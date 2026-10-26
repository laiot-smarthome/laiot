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
            'USER_ID' => $userId,
            'NAME' => $name,
            'TOKEN_HASH' => hash('sha256', $token),
            'CREATED_AT' => date('Y-m-d H:i:s', $now),
            'EXPIRES_AT' => $ttlSeconds !== null && $ttlSeconds > 0
                ? date('Y-m-d H:i:s', $now + $ttlSeconds)
                : null,
            'LAST_USED_AT' => null,
            'REVOKED_AT' => null,
        ]);

        return $token;
    }

    public function userId(string $token): ?int
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 200) {
            return null;
        }

        $row = $this->database->connection()->table('api_tokens')
            ->where('TOKEN_HASH', hash('sha256', $token))
            ->whereNull('REVOKED_AT')
            ->where(static function ($query): void {
                $query->whereNull('EXPIRES_AT')
                    ->orWhere('EXPIRES_AT', '>', date('Y-m-d H:i:s'));
            })
            ->first();

        if ($row === null) {
            return null;
        }

        $this->database->connection()->table('api_tokens')
            ->where('ID', (int)$row->ID)
            ->update(['LAST_USED_AT' => date('Y-m-d H:i:s')]);

        return (int)$row->USER_ID;
    }

    public function revoke(int $id): bool
    {
        return $this->database->connection()->table('api_tokens')
            ->where('ID', $id)
            ->whereNull('REVOKED_AT')
            ->update(['REVOKED_AT' => date('Y-m-d H:i:s')]) > 0;
    }
}
