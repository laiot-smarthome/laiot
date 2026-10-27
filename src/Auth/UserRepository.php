<?php

declare(strict_types=1);

namespace MajorDoMo\Auth;

use InvalidArgumentException;
use MajorDoMo\Database\DatabaseManager;
use Throwable;

/**
 * Пользователи MyDoMo.
 *
 * Новая таблица `users` с lowercase-колонками и timestamps; пароли —
 * password_hash/password_verify.
 *
 * Изменения:
 * 2026-10-27 — учётная запись, роли и объект Users сохраняются атомарно;
 * логин от восьми символов, пароль требует специальный символ.
 * 2026-10-27 — логин соответствует правилам имени объекта пользователя.
 * 2026-10-26 — проверка действующей сессии по пользователю и отпечатку пароля.
 */
final readonly class UserRepository
{
    public function __construct(
        private DatabaseManager $database,
        private UserObjects $objects,
        private RoleRepository $roles
    ) {
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
     * @return array{id:int,username:string,name:string,is_admin:bool,object_id:int}|null
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
            'object_id' => (int)($row->object_id ?? 0),
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
     * @return list<array{id:int,username:string,name:string,is_admin:bool,object_id:int}>
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
                'object_id' => (int)($row->object_id ?? 0),
            ])
            ->all();
    }

    /** @param list<int|string>|null $roleIds null назначает стандартную роль для CLI. */
    public function create(string $username, string $name, string $password, bool $isAdmin, ?array $roleIds = null): int
    {
        [$username, $name] = $this->validate($username, $name);
        $this->validatePassword($password);
        if ($this->exists($username)) {
            throw new InvalidArgumentException('Пользователь с таким логином уже есть.');
        }

        $now = date('Y-m-d H:i:s');

        return $this->database->transaction(function () use ($username, $name, $password, $isAdmin, $roleIds, $now): int {
            $objectId = $this->objects->create($username, $name);
            $id = (int)$this->database->connection()->table('users')->insertGetId([
                'username' => $username,
                'name' => $name,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'is_admin' => $isAdmin ? 1 : 0,
                'is_default' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
                'object_id' => $objectId,
            ]);
            if ($roleIds === null) {
                $roleId = $this->database->connection()->table('roles')
                    ->where('name', $isAdmin ? 'admin' : 'user')->whereNull('deleted_at')->value('id');
                if ($roleId === null) {
                    throw new InvalidArgumentException('Стандартная роль не найдена. Примените миграции ядра.');
                }
                $roleIds = [(int)$roleId];
            }
            $this->roles->syncUserRoles($id, $roleIds);

            return $id;
        });
    }

    /** @param list<int|string>|null $roleIds null сохраняет текущие назначения. */
    public function update(int $id, string $username, string $name, ?string $password, bool $isAdmin, ?array $roleIds = null): void
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

        try {
            $this->database->transaction(function () use ($id, $username, $values, $roleIds, $existing): void {
                $this->database->connection()->table('users')->where('id', $id)->update($values);
                if ($roleIds !== null) {
                    $this->roles->syncUserRoles($id, $roleIds);
                }
                $this->objects->rename($existing['object_id'], $username);
            });
        } catch (Throwable $error) {
            $this->objects->cancelRename();
            throw $error;
        }
        $this->objects->finishRename();
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
        $user = $this->find($id);
        if ($user === null) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $this->database->transaction(function () use ($id, $user, $now): void {
            $connection = $this->database->connection();
            $connection->table('users')->where('id', $id)->update([
                'username' => $user['username'] . '~deleted~' . $id, 'deleted_at' => $now, 'updated_at' => $now,
            ]);
            $connection->table('user_roles')->where('user_id', $id)->delete();
            $connection->table('api_tokens')->where('user_id', $id)->update(['revoked_at' => $now, 'updated_at' => $now]);
            $this->objects->remove($user['object_id']);
        });
    }

    /**
     * @return array{0:string,1:string}
     */
    private function validate(string $username, string $name): array
    {
        $username = trim($username);
        $name = trim($name);
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{7,63}$/D', $username)) {
            throw new InvalidArgumentException('Логин: 8–64 символа, латиница, цифры и _, первый символ — буква или _.');
        }
        if ($name === '' || mb_strlen($name) > 191) {
            throw new InvalidArgumentException('Имя обязательно, до 191 символа.');
        }

        return [$username, $name];
    }

    private function validatePassword(string $password): void
    {
        if (mb_strlen($password) < 8 || preg_match('/[\p{P}\p{S}]/u', $password) !== 1) {
            throw new InvalidArgumentException('Пароль — минимум 8 символов, включая хотя бы один специальный символ.');
        }
        if (strlen($password) > 72 || str_contains($password, "\0")) {
            throw new InvalidArgumentException('Пароль должен быть не длиннее 72 байт и не содержать нулевой символ.');
        }
    }

    private function exists(string $username, ?int $exceptId = null): bool
    {
        $query = $this->database->connection()->table('users')
            ->whereRaw('LOWER(username) = ?', [strtolower($username)])
            ->whereNull('deleted_at');
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }
}
