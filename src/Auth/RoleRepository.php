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
            ->join('role_permissions', 'role_permissions.PERMISSION_ID', '=', 'permissions.ID')
            ->join('user_roles', 'user_roles.ROLE_ID', '=', 'role_permissions.ROLE_ID')
            ->where('user_roles.USER_ID', $userId)
            ->pluck('permissions.NAME')
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
            ->join('user_roles', 'user_roles.ROLE_ID', '=', 'roles.ID')
            ->where('user_roles.USER_ID', $userId)
            ->pluck('roles.NAME')
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
            $connection->table('user_roles')->where('USER_ID', $userId)->delete();
            foreach (array_unique($roleIds) as $roleId) {
                $connection->table('user_roles')->insertOrIgnore([
                    'USER_ID' => $userId,
                    'ROLE_ID' => (int)$roleId,
                ]);
            }
        });
    }
}
