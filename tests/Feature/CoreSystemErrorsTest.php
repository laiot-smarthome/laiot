<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Logging\ErrorRepository;
use PHPUnit\Framework\TestCase;

/**
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 */
final class CoreSystemErrorsTest extends TestCase
{
    public function testErrorsAreListedAndSoftDeleted(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'cache' => ['driver' => 'array'],
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        $app->database()->schema()->create('system_errors', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('level')->default('error');
            $table->text('message');
            $table->text('context')->nullable();
            $table->string('request_id')->default('');
            $table->timestamps();
            $table->softDeletes();
        });
        $app->container()->make(ErrorRepository::class)->record('error', 'Тестовая ошибка', [], 'req-test');

        SessionFixture::authenticate($app);

        $page = $app->handle(Request::create('/errors', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('Тестовая ошибка', (string)$page->getContent());

        $delete = $app->handle(Request::create('/errors/1/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNull($app->database()->connection()->table('system_errors')->whereNull('deleted_at')->first());
    }
}
