<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class ActionAuditTest extends TestCase
{
    public function testLoginIsWrittenToActionLog(): void
    {
        $session = new ArraySession();
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $session,
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        $app->database()->schema()->create('users', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username')->unique();
            $table->string('name')->default('');
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        $app->database()->schema()->create('action_log', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('type');
            $table->string('title');
            $table->string('user_name');
            $table->string('terminal');
            $table->string('ip');
            $table->timestamps();
            $table->softDeletes();
        });
        $app->database()->connection()->table('users')->insert([
            'username' => 'admin', 'name' => 'Admin', 'password' => password_hash('secret123', PASSWORD_DEFAULT),
            'is_admin' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $app->handle(Request::create('/login', 'POST', [
            '_token' => $session->csrfToken(),
            'username' => 'admin',
            'password' => 'secret123',
        ]));

        self::assertSame(302, $response->getStatusCode());
        $row = $app->database()->connection()->table('action_log')->where('type', 'login.success')->first();
        self::assertNotNull($row);
        self::assertSame('admin', $row->user_name);
    }
}
