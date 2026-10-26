<?php

declare(strict_types=1);

namespace MajorDoMo\Auth;

use MajorDoMo\Database\DatabaseManager;

/**
 * Роли и разрешения пользователя.
 *
 * Пока это таблицы roles/permissions/role_permissions/user_roles; миграция
 * переносит существующих администраторов в роль admin.
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
            ->where('user_roles.user_id', $userId)
            ->pluck('permissions.name')
            ->map(static fn (mixed $name): string => (string)$name)
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
            ->pluck('roles.name')
            ->map(static fn (mixed $name): string => (string)$name)
            ->values()
            ->all();
    }

    /**
     * @param list<int> $roleIds
     */
    public function syncUserRoles(int $userId, array $roleIds): void
    {
        $connection = $this->database->connection();
        $connection->transaction(function () use ($connection, $userId, $roleIds): void {
            $connection->table('user_roles')->where('user_id', $userId)->delete();
            $now = date('Y-m-d H:i:s');
            foreach (array_unique($roleIds) as $roleId) {
                $connection->table('user_roles')->insertOrIgnore([
                    'user_id' => $userId,
                    'role_id' => (int)$roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }
}
