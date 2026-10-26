<?php

declare(strict_types=1);

namespace MajorDoMo\Auth;

use InvalidArgumentException;
use MajorDoMo\Database\DatabaseManager;

/**
 * Пользователи MyDoMo.
 *
 * Новая таблица `users` с lowercase-колонками и timestamps; пароли —
 * password_hash/password_verify.
 *
 * Изменения:
 * 2026-10-26 — проверка действующей сессии по пользователю и отпечатку пароля.
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

    /**
     * Данные активного администратора для проверки и обновления сессии.
     *
     * @return array{id:int,username:string,name:string,stamp:string,is_admin:bool}|null
     */
    public function sessionUser(int $id): ?array
    {
        $row = $this->database->connection()->table('users')
            ->where('id', $id)
            ->where('is_admin', 1)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : [
            'id' => (int)$row->id,
            'username' => (string)$row->username,
            'name' => (string)$row->name,
            'stamp' => hash('sha256', (string)$row->password),
            'is_admin' => true,
        ];
    }

    /**
     * @return list<array{id:int,username:string,name:string,is_admin:bool}>
     */
    public function all(string $search = ''): array
    {
        $query = $this->database->connection()->table('users')->whereNull('deleted_at');
        if ($search !== '') {
            $query->where(static function ($query) use ($search): void {
                $query->where('username', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%');
            });
        }

        return $query->orderBy('username')
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'username' => (string)$row->username,
                'name' => (string)$row->name,
                'is_admin' => (int)$row->is_admin === 1,
            ])
            ->all();
    }

    public function create(string $username, string $name, string $password, bool $isAdmin): int
    {
        [$username, $name] = $this->validate($username, $name);
        $this->validatePassword($password);
        if ($this->exists($username)) {
            throw new InvalidArgumentException('Пользователь с таким логином уже есть.');
        }

        $now = date('Y-m-d H:i:s');

        return (int)$this->database->connection()->table('users')->insertGetId([
            'username' => $username,
            'name' => $name,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'is_admin' => $isAdmin ? 1 : 0,
            'is_default' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function update(int $id, string $username, string $name, ?string $password, bool $isAdmin): void
    {
        [$username, $name] = $this->validate($username, $name);
        $existing = $this->find($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Пользователь не найден.');
        }
        if ($this->exists($username, $id)) {
            throw new InvalidArgumentException('Пользователь с таким логином уже есть.');
        }
        if ($password !== null && $password !== '') {
            $this->validatePassword($password);
        }

        $values = [
            'username' => $username,
            'name' => $name,
            'is_admin' => $isAdmin ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($password !== null && $password !== '') {
            $values['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $this->database->connection()->table('users')->where('id', $id)->update($values);
    }

    public function changeProfile(int $id, string $name, ?string $password = null): void
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 191) {
            throw new InvalidArgumentException('Имя обязательно, до 191 символа.');
        }
        if ($this->find($id) === null) {
            throw new InvalidArgumentException('Пользователь не найден.');
        }
        if ($password !== null && $password !== '') {
            $this->validatePassword($password);
        }

        $values = ['name' => $name, 'updated_at' => date('Y-m-d H:i:s')];
        if ($password !== null && $password !== '') {
            $values['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $this->database->connection()->table('users')->where('id', $id)->update($values);
    }

    public function delete(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('users')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function validate(string $username, string $name): array
    {
        $username = trim($username);
        $name = trim($name);
        if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/D', $username)) {
            throw new InvalidArgumentException('Логин: 3–64 символа, латиница, цифры, точка, дефис, подчёркивание.');
        }
        if ($name === '' || mb_strlen($name) > 191) {
            throw new InvalidArgumentException('Имя обязательно, до 191 символа.');
        }

        return [$username, $name];
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Пароль — минимум 8 символов.');
        }
    }

    private function exists(string $username, ?int $exceptId = null): bool
    {
        $query = $this->database->connection()->table('users')
            ->where('username', $username)
            ->whereNull('deleted_at');
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }
}
