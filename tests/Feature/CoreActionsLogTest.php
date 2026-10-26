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
    public function testLegacyLogIsRenderedAndDeleted(): void
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
        $app->database()->schema()->create('actions_log', static function (Blueprint $table): void {
            $table->increments('ID');
            $table->dateTime('ADDED')->nullable();
            $table->string('ACTION_TYPE')->default('');
            $table->string('TITLE')->default('');
            $table->string('USER')->default('');
            $table->string('TERMINAL')->default('');
            $table->string('IP')->default('');
        });
        $app->database()->connection()->table('actions_log')->insert([
            'ADDED' => date('Y-m-d H:i:s'),
            'ACTION_TYPE' => 'login',
            'TITLE' => 'Вход администратора',
            'USER' => 'admin',
            'TERMINAL' => 'web',
            'IP' => '127.0.0.1',
        ]);

        $page = $app->handle(Request::create('/module/actions_log', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('Вход администратора', (string)$page->getContent());

        $delete = $app->handle(Request::create('/module/actions_log/1/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertSame(0, $app->database()->connection()->table('actions_log')->count());
    }
}
