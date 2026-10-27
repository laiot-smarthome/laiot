<?php

declare(strict_types=1);

namespace MajorDoMo\Modules;

use MajorDoMo\Database\DatabaseManager;
use Throwable;

/**
 * Разрешения, объявленные в манифесте модуля.
 *
 * Манифест — декларация для UI и ролей, а не политика: при установке и
 * включении имена попадают в таблицу `permissions` с владельцем `module_id`.
 * Администратор получает их через роль admin (`*`), остальным роль выдаётся
 * вручную. При удалении модуля удаляются только его разрешения, на которые
 * не ссылается ни одна роль.
 *
 * 2026-10-27 — синхронизация permissions из module.json.
 */
final readonly class ModulePermissions
{
    public function __construct(private DatabaseManager $database)
    {
    }

    /**
     * @param list<string> $permissions
     * @return list<string> Зарегистрированные имена.
     */
    public function register(string $moduleId, array $permissions): array
    {
        if (!$this->available()) {
            return [];
        }
        $connection = $this->database->connection();
        $hasOwner = $connection->getSchemaBuilder()->hasColumn('permissions', 'module_id');
        $now = date('Y-m-d H:i:s');
        $registered = [];

        foreach (array_values(array_unique($permissions)) as $name) {
            if ($name === '') {
                continue;
            }
            $record = [
                'name' => mb_substr($name, 0, 191),
                'title' => mb_substr($name, 0, 191),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
            if ($hasOwner) {
                $record['module_id'] = $moduleId;
            }
            $connection->table('permissions')->insertOrIgnore($record);
            $registered[] = $name;
        }

        return $registered;
    }

    /**
     * Удаляет разрешения модуля, которые не выданы ни одной роли.
     *
     * @param list<string> $permissions
     */
    public function forget(string $moduleId, array $permissions): int
    {
        if (!$this->available() || $permissions === []) {
            return 0;
        }
        $connection = $this->database->connection();
        if (!$connection->getSchemaBuilder()->hasColumn('permissions', 'module_id')) {
            return 0;
        }

        $ids = $connection->table('permissions')
            ->where('module_id', $moduleId)
            ->whereIn('name', array_values(array_unique($permissions)))
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int)$id)
            ->all();
        if ($ids === []) {
            return 0;
        }

        $used = $connection->table('role_permissions')
            ->whereIn('permission_id', $ids)
            ->pluck('permission_id')
            ->map(static fn (mixed $id): int => (int)$id)
            ->all();
        $removable = array_values(array_diff($ids, $used));
        if ($removable === []) {
            return 0;
        }

        return (int)$connection->table('permissions')->whereIn('id', $removable)->delete();
    }

    public function available(): bool
    {
        try {
            return $this->database->isConfigured()
                && $this->database->connection()->getSchemaBuilder()->hasTable('permissions');
        } catch (Throwable) {
            return false;
        }
    }
}
