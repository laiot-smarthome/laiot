<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Notifications\NotificationService;
use MajorDoMo\Panel\PanelAttention;
use PHPUnit\Framework\TestCase;

/**
 * Счётчики внимания бокового меню: сервис и API оболочки.
 *
 * 2026-10-27 — проверяется, что бейджи считают ошибки за сутки, сбои очереди и
 * непрочитанные уведомления, а эндпоинт закрыт сессией администратора.
 */
final class PanelAttentionTest extends TestCase
{
    public function testCountsErrorsNotificationsAndJobs(): void
    {
        $app = $this->boot();
        $connection = $app->database()->connection();
        $now = date('Y-m-d H:i:s');
        $connection->table('system_errors')->insert([
            ['level' => 'error', 'message' => 'Свежая', 'request_id' => '', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
            ['level' => 'error', 'message' => 'Тоже свежая', 'request_id' => '', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
            ['level' => 'error', 'message' => 'Старая', 'request_id' => '', 'created_at' => date('Y-m-d H:i:s', time() - 3 * 86400), 'updated_at' => $now, 'deleted_at' => null],
            ['level' => 'error', 'message' => 'Удалённая', 'request_id' => '', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => $now],
        ]);
        $app->container()->make(NotificationService::class)->send('events', 'Ошибка сценария', 'danger');

        $badges = $app->container()->make(PanelAttention::class)->badges();

        self::assertSame(2, $badges['system_errors']['count'], 'Ошибки за сутки и без удалённых.');
        self::assertSame('danger', $badges['system_errors']['tone']);
        self::assertSame(1, $badges['notifications']['count']);
        self::assertSame('danger', $badges['notifications']['tone']);
        self::assertSame(0, $badges['jobs']['count']);
        self::assertSame('warning', $badges['jobs']['tone']);
    }

    public function testEndpointReturnsBadgesForSession(): void
    {
        $app = $this->boot();
        $now = date('Y-m-d H:i:s');
        $app->database()->connection()->table('system_errors')->insert([
            'level' => 'error', 'message' => 'Ошибка', 'request_id' => '', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
        ]);

        $response = $app->handle(Request::create('/api/v1/panel/attention', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]));
        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok'] ?? false);
        self::assertSame(1, $payload['data']['badges']['system_errors']['count'] ?? null);

        $guest = $this->boot(false);
        $unauthorized = $guest->handle(Request::create('/api/v1/panel/attention', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]));
        self::assertSame(401, $unauthorized->getStatusCode());
    }

    private function boot(bool $login = true): Application
    {
        $session = new ArraySession();
        if ($login) {
            $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Администратор', 'is_admin' => true]);
        }

        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $session,
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        $app->database()->schema()->create('system_errors', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('level', 16)->default('error');
            $table->text('message');
            $table->text('context')->nullable();
            $table->string('request_id', 64)->default('');
            $table->timestamps();
            $table->softDeletes();
        });
        $app->database()->schema()->create('notifications', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('module', 100)->default('');
            $table->string('type', 20)->default('info');
            $table->string('message', 255);
            $table->string('title', 200)->nullable();
            $table->text('details')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('label', 100)->nullable();
            $table->string('notification_key', 190)->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        SessionFixture::authenticate($app);

        return $app;
    }
}
