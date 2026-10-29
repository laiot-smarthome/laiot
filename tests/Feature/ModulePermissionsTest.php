<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Modules\ModulePermissions;
use PHPUnit\Framework\TestCase;

/**
 * Разрешения из манифеста: регистрация, владелец и удаление без чужих.
 *
 * 2026-10-27 — тест создан вместе с ModulePermissions.
 */
final class ModulePermissionsTest extends TestCase
{
    private DatabaseManager $database;
    private ModulePermissions $permissions;

    protected function setUp(): void
    {
        $this->database = new DatabaseManager([
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        foreach ([
            '2026_10_26_000003_create_roles.php',
            '2026_10_27_000006_add_permissions_module_id.php',
        ] as $name) {
            $migration = require dirname(__DIR__, 2) . '/database/migrations/' . $name;
            $migration($this->database->connection());
        }
        $this->permissions = new ModulePermissions($this->database);
    }

    public function testRegisterAddsOwnedPermissionsIdempotently(): void
    {
        self::assertSame(
            ['demo.view', 'demo.manage'],
            $this->permissions->register('demo', ['demo.view', 'demo.manage'])
        );
        $this->permissions->register('demo', ['demo.view']);

        $rows = $this->database->connection()->table('permissions')
            ->where('module_id', 'demo')
            ->orderBy('name')
            ->get();
        self::assertCount(2, $rows);
        self::assertSame('demo', (string)$rows[0]->module_id);
    }

    public function testForgetRemovesOnlyUnusedPermissions(): void
    {
        $this->permissions->register('demo', ['demo.view', 'demo.manage']);

        self::assertSame(2, $this->permissions->forget('demo', ['demo.view', 'demo.manage']));
        self::assertSame(0, $this->database->connection()->table('permissions')
            ->where('module_id', 'demo')
            ->count());
    }

    public function testPermissionGrantedToRoleSurvivesModuleRemoval(): void
    {
        $this->permissions->register('demo', ['demo.view']);
        $permissionId = (int)$this->database->connection()->table('permissions')
            ->where('name', 'demo.view')
            ->value('id');
        $roleId = (int)$this->database->connection()->table('roles')->where('name', 'admin')->value('id');
        $this->database->connection()->table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        self::assertSame(0, $this->permissions->forget('demo', ['demo.view']));
        self::assertSame(1, $this->database->connection()->table('permissions')
            ->where('name', 'demo.view')
            ->count());
    }
}
