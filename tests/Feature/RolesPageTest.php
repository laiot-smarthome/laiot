<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class RolesPageTest extends TestCase
{
    public function testAdminSeesRolesAndPermissions(): void
    {
        $app = $this->app(true);
        $response = $app->handle(Request::create('/roles', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Роли и права', (string)$response->getContent());
        self::assertStringContainsString('settings.manage', (string)$response->getContent());
    }

    public function testUserWithoutPermissionIsForbidden(): void
    {
        $app = $this->app(false);
        $response = $app->handle(Request::create('/roles', 'GET'));

        self::assertSame(403, $response->getStatusCode());
    }

    private function app(bool $admin): Application
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'user', 'name' => 'User', 'is_admin' => $admin]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        $app->database()->schema()->create('roles', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('title');
            $table->dateTime('deleted_at')->nullable();
        });
        $app->database()->schema()->create('permissions', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('title');
            $table->dateTime('deleted_at')->nullable();
        });
        $app->database()->connection()->table('roles')->insert(['id' => 1, 'name' => 'admin', 'title' => 'Администратор']);
        $app->database()->connection()->table('permissions')->insert(['id' => 1, 'name' => 'settings.manage', 'title' => 'Настройки']);

        return $app;
    }
}
