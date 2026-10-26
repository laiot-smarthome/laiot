<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();

    if (!$schema->hasTable('roles')) {
        $schema->create('roles', static function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('NAME', 64)->unique();
            $table->string('TITLE', 191);
            $table->boolean('IS_SYSTEM')->default(false);
        });
    }

    if (!$schema->hasTable('permissions')) {
        $schema->create('permissions', static function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('NAME', 191)->unique();
            $table->string('TITLE', 191);
        });
    }

    if (!$schema->hasTable('role_permissions')) {
        $schema->create('role_permissions', static function (Blueprint $table): void {
            $table->integer('ROLE_ID');
            $table->integer('PERMISSION_ID');
            $table->unique(['ROLE_ID', 'PERMISSION_ID']);
        });
    }

    if (!$schema->hasTable('user_roles')) {
        $schema->create('user_roles', static function (Blueprint $table): void {
            $table->integer('USER_ID');
            $table->integer('ROLE_ID');
            $table->unique(['USER_ID', 'ROLE_ID']);
        });
    }

    $roles = $connection->table('roles');
    $roles->insertOrIgnore([
        ['NAME' => 'admin', 'TITLE' => 'Администратор', 'IS_SYSTEM' => 1],
        ['NAME' => 'user', 'TITLE' => 'Пользователь', 'IS_SYSTEM' => 1],
    ]);

    $permissions = $connection->table('permissions');
    $permissions->insertOrIgnore([
        ['NAME' => '*', 'TITLE' => 'Все действия'],
        ['NAME' => 'panel.view', 'TITLE' => 'Просмотр панели'],
        ['NAME' => 'settings.manage', 'TITLE' => 'Управление настройками'],
        ['NAME' => 'users.manage', 'TITLE' => 'Управление пользователями'],
    ]);

    $adminRole = $connection->table('roles')->where('NAME', 'admin')->value('ID');
    $userRole = $connection->table('roles')->where('NAME', 'user')->value('ID');
    $all = $connection->table('permissions')->where('NAME', '*')->value('ID');
    $panel = $connection->table('permissions')->where('NAME', 'panel.view')->value('ID');

    if ($adminRole !== null && $all !== null) {
        $connection->table('role_permissions')->insertOrIgnore([
            'ROLE_ID' => (int)$adminRole,
            'PERMISSION_ID' => (int)$all,
        ]);
    }
    if ($userRole !== null && $panel !== null) {
        $connection->table('role_permissions')->insertOrIgnore([
            'ROLE_ID' => (int)$userRole,
            'PERMISSION_ID' => (int)$panel,
        ]);
    }

    if (!$schema->hasTable('users')) {
        return;
    }

    if ($userRole !== null) {
        $userIds = $connection->table('users')->pluck('ID');
        foreach ($userIds as $userId) {
            $connection->table('user_roles')->insertOrIgnore([
                'USER_ID' => (int)$userId,
                'ROLE_ID' => (int)$userRole,
            ]);
        }
    }

    if ($adminRole !== null) {
        $adminIds = $connection->table('users')->where('IS_ADMIN', 1)->pluck('ID');
        foreach ($adminIds as $userId) {
            $connection->table('user_roles')->insertOrIgnore([
                'USER_ID' => (int)$userId,
                'ROLE_ID' => (int)$adminRole,
            ]);
        }
    }
};
