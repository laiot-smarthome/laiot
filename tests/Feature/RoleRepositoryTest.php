<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Auth\RoleRepository;
use PHPUnit\Framework\TestCase;

final class RoleRepositoryTest extends TestCase
{
    private DatabaseManager $database;
    private RoleRepository $roles;

    protected function setUp(): void
    {
        $this->database = new DatabaseManager([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->database->schema()->create('roles', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });
        $this->database->schema()->create('permissions', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });
        $this->database->schema()->create('role_permissions', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('role_id');
            $table->integer('permission_id');
            $table->timestamps();
            $table->softDeletes();
        });
        $this->database->schema()->create('user_roles', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('role_id');
            $table->timestamps();
            $table->softDeletes();
        });

        $connection = $this->database->connection();
        $connection->table('roles')->insert([
            ['id' => 1, 'name' => 'admin', 'title' => 'Администратор'],
            ['id' => 2, 'name' => 'user', 'title' => 'Пользователь'],
        ]);
        $connection->table('permissions')->insert([
            ['id' => 1, 'name' => '*', 'title' => 'Все'],
            ['id' => 2, 'name' => 'panel.view', 'title' => 'Панель'],
        ]);
        $connection->table('role_permissions')->insert([
            ['role_id' => 1, 'permission_id' => 1],
            ['role_id' => 2, 'permission_id' => 2],
        ]);
        $connection->table('user_roles')->insert([
            ['user_id' => 10, 'role_id' => 1],
            ['user_id' => 20, 'role_id' => 2],
        ]);

        $this->roles = new RoleRepository($this->database);
    }

    public function testPermissionsAndRolesAreResolved(): void
    {
        self::assertSame(['*'], $this->roles->permissionsForUser(10));
        self::assertSame(['panel.view'], $this->roles->permissionsForUser(20));
        self::assertSame(['admin'], $this->roles->rolesForUser(10));
        self::assertSame(['user'], $this->roles->rolesForUser(20));
    }

    public function testSyncRolesReplacesAssignments(): void
    {
        $this->roles->syncUserRoles(20, [1]);
        self::assertSame(['*'], $this->roles->permissionsForUser(20));
    }
}
