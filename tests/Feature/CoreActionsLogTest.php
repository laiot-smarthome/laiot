<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class CoreActionsLogTest extends TestCase
{
    public function testActionLogIsRenderedAndSoftDeleted(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        $app->database()->schema()->create('action_log', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('type')->default('');
            $table->string('title')->default('');
            $table->string('user_name')->default('');
            $table->string('terminal')->default('');
            $table->string('ip')->default('');
            $table->timestamps();
            $table->softDeletes();
        });
        $app->database()->connection()->table('action_log')->insert([
            'type' => 'login',
            'title' => 'Вход администратора',
            'user_name' => 'admin',
            'terminal' => 'web',
            'ip' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $page = $app->handle(Request::create('/module/actions_log', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('Вход администратора', (string)$page->getContent());

        $delete = $app->handle(Request::create('/module/actions_log/1/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNull($app->database()->connection()->table('action_log')->whereNull('deleted_at')->first());
    }
}
