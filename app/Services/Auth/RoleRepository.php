<?php

declare(strict_types=1);

namespace LaIOT\Services\Auth;

use InvalidArgumentException;
use LaIOT\Database\DatabaseManager;

/**
 * Роли и разрешения пользователя.
 *
 * 2026-10-27 — управление ролями, проверка назначений и пакетное чтение;
 * удалённые роли, права и назначения не участвуют в авторизации.
 */
final readonly class RoleRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    /**
     * @return list<string>
     */
    public function permissionsForUser(int $userId): array
    {
        return $this->database->connection()->table('permissions')
            ->join('role_permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->join('user_roles', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $userId)
            ->whereNull('roles.deleted_at')
            ->whereNull('permissions.deleted_at')
            ->whereNull('role_permissions.deleted_at')
            ->whereNull('user_roles.deleted_at')
            ->distinct()
            ->pluck('permissions.name')
            ->map(static fn (mixed $name): string => (string)$name)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function roleIdsForUser(int $userId): array
    {
        return $this->database->connection()->table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_id', $userId)
            ->whereNull('user_roles.deleted_at')
            ->whereNull('roles.deleted_at')
            ->pluck('role_id')
            ->map(static fn (mixed $id): int => (int)$id)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function rolesForUser(int $userId): array
    {
        return $this->database->connection()->table('roles')
            ->join('user_roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $userId)
            ->whereNull('user_roles.deleted_at')
            ->whereNull('roles.deleted_at')
            ->pluck('roles.name')
            ->map(static fn (mixed $name): string => (string)$name)
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,name:string,title:string,is_system:bool}>
     */
    public function allRoles(): array
    {
        return $this->database->connection()->table('roles')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'name' => (string)$row->name,
                'title' => (string)$row->title,
                'is_system' => (bool)($row->is_system ?? false),
            ])
            ->all();
    }

    /**
     * @return list<array{id:int,name:string,title:string}>
     */
    public function allPermissions(): array
    {
        return $this->database->connection()->table('permissions')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'name' => (string)$row->name,
                'title' => (string)$row->title,
            ])
            ->all();
    }

    /**
     * @param list<int|string> $roleIds
     */
    public function syncUserRoles(int $userId, array $roleIds): void
    {
        $roleIds = $this->validatedIds('roles', $roleIds);
        $connection = $this->database->connection();
        $connection->transaction(function () use ($connection, $userId, $roleIds): void {
            $connection->table('user_roles')->where('user_id', $userId)->delete();
            $now = date('Y-m-d H:i:s');
            foreach (array_unique($roleIds) as $roleId) {
                $connection->table('user_roles')->insert([
                    'user_id' => $userId,
                    'role_id' => (int)$roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    /** @return array<int,list<string>> */
    public function rolesByUser(): array
    {
        $result = [];
        $rows = $this->database->connection()->table('user_roles as ur')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->whereNull('ur.deleted_at')->whereNull('r.deleted_at')->orderBy('r.title')
            ->get(['ur.user_id', 'r.title']);
        foreach ($rows as $row) {
            $result[(int)$row->user_id][] = (string)$row->title;
        }

        return $result;
    }

    /** @return array<int,list<int>> */
    public function permissionsByRole(): array
    {
        $result = [];
        $rows = $this->database->connection()->table('role_permissions as rp')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->whereNull('rp.deleted_at')->whereNull('p.deleted_at')->get(['rp.role_id', 'rp.permission_id']);
        foreach ($rows as $row) {
            $result[(int)$row->role_id][] = (int)$row->permission_id;
        }

        return $result;
    }

    /** @return array<int,int> */
    public function userCounts(): array
    {
        $counts = [];
        $rows = $this->database->connection()->table('user_roles as ur')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->whereNull('ur.deleted_at')->whereNull('u.deleted_at')
            ->select('ur.role_id')->selectRaw('COUNT(*) AS aggregate')->groupBy('ur.role_id')->get();
        foreach ($rows as $row) {
            $counts[(int)$row->role_id] = (int)$row->aggregate;
        }

        return $counts;
    }

    /** @return array{id:int,name:string,title:string,is_system:bool}|null */
    public function find(int $id): ?array
    {
        foreach ($this->allRoles() as $role) {
            if ($role['id'] === $id) {
                return $role;
            }
        }

        return null;
    }

    /** @param array<array-key,mixed> $permissionIds */
    public function save(int $id, string $name, string $title, array $permissionIds): int
    {
        $name = trim($name);
        $title = trim($title);
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Код роли: 2–64 символа, строчные латинские буквы, цифры и _. Первый символ — буква.');
        }
        if ($title === '' || mb_strlen($title) > 191) {
            throw new InvalidArgumentException('Укажите название роли до 191 символа.');
        }
        $current = $id > 0 ? $this->find($id) : null;
        if ($id > 0 && $current === null) {
            throw new InvalidArgumentException('Роль не найдена.');
        }
        if (($current['is_system'] ?? false) && $current['name'] !== $name) {
            throw new InvalidArgumentException('Код системной роли менять нельзя.');
        }
        $connection = $this->database->connection();
        if ($connection->table('roles')->whereRaw('LOWER(name) = ?', [$name])->where('id', '<>', $id)->exists()) {
            throw new InvalidArgumentException('Такой код роли уже занят.');
        }
        $permissionIds = $this->validatedIds('permissions', $permissionIds);
        if ($name === 'admin') {
            $all = (int)$connection->table('permissions')->where('name', '*')->whereNull('deleted_at')->value('id');
            if (!in_array($all, $permissionIds, true)) {
                throw new InvalidArgumentException('Системная роль admin должна сохранять право «Все действия».');
            }
        }

        return $connection->transaction(function () use ($connection, $id, $name, $title, $permissionIds): int {
            $now = date('Y-m-d H:i:s');
            if ($id > 0) {
                $connection->table('roles')->where('id', $id)->update(['name' => $name, 'title' => $title, 'updated_at' => $now]);
            } else {
                $id = (int)$connection->table('roles')->insertGetId([
                    'name' => $name, 'title' => $title, 'is_system' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $connection->table('role_permissions')->where('role_id', $id)->delete();
            foreach ($permissionIds as $permissionId) {
                $connection->table('role_permissions')->insert([
                    'role_id' => $id, 'permission_id' => $permissionId, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            return $id;
        });
    }

    public function delete(int $id): void
    {
        $role = $this->find($id);
        if ($role === null) {
            throw new InvalidArgumentException('Роль не найдена.');
        }
        if ($role['is_system']) {
            throw new InvalidArgumentException('Системную роль удалить нельзя.');
        }
        if (($this->userCounts()[$id] ?? 0) > 0) {
            throw new InvalidArgumentException('Сначала снимите эту роль у пользователей.');
        }
        $connection = $this->database->connection();
        $connection->transaction(static function () use ($connection, $id): void {
            $connection->table('role_permissions')->where('role_id', $id)->delete();
            $connection->table('user_roles')->where('role_id', $id)->delete();
            $connection->table('roles')->where('id', $id)->delete();
        });
    }

    /**
     * @param array<array-key,mixed> $ids
     * @return list<int>
     */
    private function validatedIds(string $table, array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            if ((!is_int($id) && !is_string($id)) || preg_match('/^[1-9][0-9]*$/D', (string)$id) !== 1) {
                throw new InvalidArgumentException('Некорректный идентификатор роли или разрешения.');
            }
            $normalized[] = (int)$id;
        }
        $normalized = array_values(array_unique($normalized));
        if ($this->database->connection()->table($table)->whereIn('id', $normalized)->whereNull('deleted_at')->count() !== count($normalized)) {
            throw new InvalidArgumentException('Выбранная роль или разрешение больше недоступны.');
        }

        return $normalized;
    }
}
