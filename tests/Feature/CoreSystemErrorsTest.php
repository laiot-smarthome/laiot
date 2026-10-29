<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use LaIOT\Services\Logging\ErrorRepository;
use PHPUnit\Framework\TestCase;

/**
 * Ошибки ядра: список, группы повторов и удаление.
 *
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 * 2026-10-26 — добавлены группировка одинаковых сообщений и удаление группы.
 */
final class CoreSystemErrorsTest extends TestCase
{
    public function testErrorsAreListedAndSoftDeleted(): void
    {
        [$app, $session] = $this->boot();

        $page = $app->handle(Request::create('/errors', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('Тестовая ошибка', (string)$page->getContent());

        $delete = $app->handle(Request::create('/errors/1/delete', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNull($app->database()->connection()->table('system_errors')->whereNull('deleted_at')->first());
    }

    public function testRepeatedErrorsAreGroupedAndGroupCanBeDeleted(): void
    {
        [$app, $session] = $this->boot();
        $errors = $app->container()->make(ErrorRepository::class);
        $errors->record('error', 'Одинаковая ошибка', [], 'req-1');
        $errors->record('error', 'Одинаковая ошибка', [], 'req-2');
        $errors->record('warning', 'Другое сообщение', [], 'req-3');

        $page = $app->handle(Request::create('/errors', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        $html = (string)$page->getContent();
        self::assertStringContainsString('Одинаковая ошибка', $html);
        self::assertStringContainsString('Групп: 3', $html);

        $flat = $app->handle(Request::create('/errors', 'GET', ['flat' => '1']));
        self::assertStringContainsString('записей: 4', (string)$flat->getContent());

        $delete = $app->handle(Request::create('/errors/group-delete', 'POST', [
            '_token' => $session->csrfToken(),
            'level' => 'error',
            'message' => 'Одинаковая ошибка',
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertSame(2, (int)$app->database()->connection()->table('system_errors')->whereNull('deleted_at')->count());
    }

    /**
     * @return array{0:Application,1:ArraySession}
     */
    private function boot(): array
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

        return [$app, $session];
    }
}
