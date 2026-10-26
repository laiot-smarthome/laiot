<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();

    if (!$schema->hasTable('roles')) {
        $schema->create('roles', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name', 64)->unique();
            $table->string('title', 191);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    if (!$schema->hasTable('permissions')) {
        $schema->create('permissions', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name', 191)->unique();
            $table->string('title', 191);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    if (!$schema->hasTable('role_permissions')) {
        $schema->create('role_permissions', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('role_id');
            $table->integer('permission_id');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['role_id', 'permission_id']);
        });
    }

    if (!$schema->hasTable('user_roles')) {
        $schema->create('user_roles', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('role_id');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'role_id']);
        });
    }

    $now = date('Y-m-d H:i:s');
    $roles = $connection->table('roles');
    foreach ([['admin', 'Администратор'], ['user', 'Пользователь']] as [$name, $title]) {
        if (!$roles->where('name', $name)->exists()) {
            $roles->insert([
                'name' => $name,
                'title' => $title,
                'is_system' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    $permissions = $connection->table('permissions');
    foreach ([['*', 'Все действия'], ['panel.view', 'Просмотр панели'], ['settings.manage', 'Управление настройками'], ['users.manage', 'Управление пользователями']] as [$name, $title]) {
        if (!$permissions->where('name', $name)->exists()) {
            $permissions->insert([
                'name' => $name,
                'title' => $title,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    $adminRole = $connection->table('roles')->where('name', 'admin')->value('id');
    $userRole = $connection->table('roles')->where('name', 'user')->value('id');
    $all = $connection->table('permissions')->where('name', '*')->value('id');
    $panel = $connection->table('permissions')->where('name', 'panel.view')->value('id');

    $rolePermissions = $connection->table('role_permissions');
    if ($adminRole !== null && $all !== null && !$rolePermissions->where('role_id', $adminRole)->where('permission_id', $all)->exists()) {
        $rolePermissions->insert([
            'role_id' => (int)$adminRole,
            'permission_id' => (int)$all,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    if ($userRole !== null && $panel !== null && !$rolePermissions->where('role_id', $userRole)->where('permission_id', $panel)->exists()) {
        $rolePermissions->insert([
            'role_id' => (int)$userRole,
            'permission_id' => (int)$panel,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

};
