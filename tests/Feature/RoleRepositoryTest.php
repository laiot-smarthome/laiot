<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use MajorDoMo\Auth\RoleRepository;
use MajorDoMo\Database\DatabaseManager;
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
            $table->increments('ID');
            $table->string('NAME');
            $table->string('TITLE');
        });
        $this->database->schema()->create('permissions', static function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('NAME');
            $table->string('TITLE');
        });
        $this->database->schema()->create('role_permissions', static function (Blueprint $table): void {
            $table->integer('ROLE_ID');
            $table->integer('PERMISSION_ID');
        });
        $this->database->schema()->create('user_roles', static function (Blueprint $table): void {
            $table->integer('USER_ID');
            $table->integer('ROLE_ID');
        });

        $connection = $this->database->connection();
        $connection->table('roles')->insert([
            ['ID' => 1, 'NAME' => 'admin', 'TITLE' => 'Администратор'],
            ['ID' => 2, 'NAME' => 'user', 'TITLE' => 'Пользователь'],
        ]);
        $connection->table('permissions')->insert([
            ['ID' => 1, 'NAME' => '*', 'TITLE' => 'Все'],
            ['ID' => 2, 'NAME' => 'panel.view', 'TITLE' => 'Панель'],
        ]);
        $connection->table('role_permissions')->insert([
            ['ROLE_ID' => 1, 'PERMISSION_ID' => 1],
            ['ROLE_ID' => 2, 'PERMISSION_ID' => 2],
        ]);
        $connection->table('user_roles')->insert([
            ['USER_ID' => 10, 'ROLE_ID' => 1],
            ['USER_ID' => 20, 'ROLE_ID' => 2],
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
