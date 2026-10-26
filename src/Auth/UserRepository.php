<?php

declare(strict_types=1);

namespace MajorDoMo\Auth;

use MajorDoMo\Database\DatabaseManager;

/**
 * Пользователи MyDoMo.
 *
 * Новая таблица `users` с lowercase-колонками и timestamps; пароли —
 * password_hash/password_verify.
 */
final readonly class UserRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    /**
     * @return array{id:int,username:string,name:string,stamp:string,is_admin:bool}|null
     */
    public function verifyAdmin(string $username, string $password): ?array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return null;
        }

        $row = $this->database->connection()->table('users')
            ->where('username', $username)
            ->whereNull('deleted_at')
            ->first();

        if ($row === null || (int)$row->is_admin !== 1 || !password_verify($password, (string)$row->password)) {
            return null;
        }

        return [
            'id' => (int)$row->id,
            'username' => (string)$row->username,
            'name' => (string)$row->name,
            'stamp' => hash('sha256', (string)$row->password),
            'is_admin' => true,
        ];
    }

    /**
     * @return array{id:int,username:string,name:string,is_admin:bool}|null
     */
    public function find(int $id): ?array
    {
        $row = $this->database->connection()->table('users')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : [
            'id' => (int)$row->id,
            'username' => (string)$row->username,
            'name' => (string)$row->name,
            'is_admin' => (int)$row->is_admin === 1,
        ];
    }
}
