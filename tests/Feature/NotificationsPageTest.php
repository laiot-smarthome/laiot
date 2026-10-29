<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Notifications\NotificationService;
use PHPUnit\Framework\TestCase;

/**
 * Страница «Оповещения» заменила демо-Shoutbox.
 *
 * 2026-10-29 — тестовая схема хранит важность уведомления.
 */
final class NotificationsPageTest extends TestCase
{
    public function testNotificationCanBeReadFromPage(): void
    {
        $session = new ArraySession();
        $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Администратор', 'is_admin' => true]);
        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'cache' => ['driver' => 'array'],
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $app->boot();
        $app->database()->schema()->create('notifications', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('module', 100)->default('');
            $table->string('type', 20)->default('info');
            $table->string('message', 255);
            $table->unsignedTinyInteger('importance')->default(5);
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

        $service = $app->container()->make(NotificationService::class);
        $sent = $service->send('events', 'Проверка оповещений', 'warning', ['title' => 'Событие']);
        self::assertTrue($sent['status']);

        $page = $app->handle(Request::create('/notifications', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('Оповещения', (string)$page->getContent());
        self::assertStringContainsString('Проверка оповещений', (string)$page->getContent());

        $dismiss = $app->handle(Request::create('/notifications/' . $sent['id'] . '/dismiss', 'POST', [
            '_token' => $session->csrfToken(),
        ]));
        self::assertSame(302, $dismiss->getStatusCode());
        self::assertSame(0, $service->summary()['total']);
    }
}
