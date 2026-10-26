<?php

declare(strict_types=1);

use Illuminate\Database\Connection;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if (!$schema->hasTable('users') || !$schema->hasTable('user_roles')) {
        return;
    }

    $now = date('Y-m-d H:i:s');
    $users = $connection->table('users')->whereNull('deleted_at');
    $userRole = $connection->table('roles')->where('name', 'user')->value('id');
    $adminRole = $connection->table('roles')->where('name', 'admin')->value('id');

    foreach ($users->get() as $user) {
        $roleId = (int)$user->is_admin === 1 ? $adminRole : $userRole;
        if ($roleId === null) {
            continue;
        }
        $exists = $connection->table('user_roles')
            ->where('user_id', (int)$user->id)
            ->where('role_id', (int)$roleId)
            ->exists();
        if (!$exists) {
            $connection->table('user_roles')->insert([
                'user_id' => (int)$user->id,
                'role_id' => (int)$roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
