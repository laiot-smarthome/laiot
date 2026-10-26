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
 * Центр уведомлений: сервис, дедупликация/лимит, API, CSRF и разметка колокольчика.
 *
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 */
final class NotificationsTest extends TestCase
{
    public function testSendAndListing(): void
    {
        $app = $this->boot();
        $service = $app->container()->make(NotificationService::class);

        $sent = $service->send('events', 'Цикл остановлен', 'warning', [
            'key' => 'chain:1',
            'title' => 'Цепочка события',
            'details' => 'Кухня.temperature',
            'url' => '/events',
            'label' => 'Открыть журнал',
        ]);
        self::assertTrue($sent['status']);
        self::assertGreaterThan(0, (int)($sent['id'] ?? 0));

        $listing = $service->listing();
        self::assertSame(1, $listing['total']);
        self::assertSame(1, $listing['counts']['events'] ?? 0);
        self::assertSame('warning', $listing['levels']['events'] ?? '');
        self::assertSame('warning', $listing['severity']);

        $item = $listing['items'][0];
        self::assertSame('events', $item['module']);
        self::assertSame('События', $item['module_title']);
        self::assertSame('/events', $item['module_url']);
        self::assertSame('Цикл остановлен', $item['message']);
        self::assertSame('warning', $item['type']);
        self::assertSame('Цепочка события', $item['title']);
        self::assertSame('/events', $item['url']);
        self::assertSame('Открыть журнал', $item['label']);
    }

    public function testDuplicateAndUnreadLimit(): void
    {
        $app = $this->boot();
        $service = $app->container()->make(NotificationService::class);

        $first = $service->send('events', 'Первое', 'info', ['key' => 'same']);
        $second = $service->send('events', 'Другое сообщение', 'info', ['key' => 'same']);
        self::assertTrue($first['status']);
        self::assertFalse($second['status']);
        self::assertSame((int)$first['id'], (int)($second['id'] ?? 0));

        for ($i = 1; $i <= 9; $i++) {
            self::assertTrue($service->send('events', 'Сообщение ' . $i, 'info')['status']);
        }
        self::assertSame(NotificationService::MAX_UNREAD, $service->summary()['total']);
        $overflow = $service->send('events', 'Одиннадцатое', 'info');
        self::assertFalse($overflow['status']);
        self::assertStringContainsString('10', (string)($overflow['error'] ?? ''));
    }

    public function testTypeAndUrlAreNormalized(): void
    {
        $app = $this->boot();
        $service = $app->container()->make(NotificationService::class);

        self::assertTrue($service->send('system', '<b>Важно</b>', 'default')['status']);
        self::assertTrue($service->send('events', 'Ошибка', 'error')['status']);
        $byMessage = [];
        foreach ($service->listing()['items'] as $item) {
            $byMessage[$item['message']] = $item;
        }
        self::assertSame('info', $byMessage['Важно']['type'] ?? '');
        self::assertSame('danger', $byMessage['Ошибка']['type'] ?? '');

        $bad = $service->send('events', 'Плохая ссылка', 'info', ['url' => 'https://example.com']);
        self::assertFalse($bad['status']);
        self::assertSame('', NotificationService::internalUrl('//evil'));
        self::assertSame('/events', NotificationService::internalUrl('/events'));
    }

    public function testDismissByIdAndModule(): void
    {
        $app = $this->boot();
        $service = $app->container()->make(NotificationService::class);
        $first = $service->send('events', 'Первое', 'info');
        $service->send('events', 'Второе', 'info');

        self::assertSame(1, $service->dismiss((int)$first['id']));
        self::assertSame(1, $service->summary()['total']);
        self::assertSame(1, $service->dismiss(null, 'events'));
        self::assertSame(0, $service->summary()['total']);
    }

    public function testApiEnvelopeAndAuthentication(): void
    {
        $app = $this->boot();
        $app->container()->make(NotificationService::class)->send('events', 'Проверка', 'success');

        $response = $app->handle(Request::create('/api/v1/panel/notifications', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]));
        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            self::fail('Ответ уведомлений должен быть JSON-объектом.');
        }
        self::assertTrue($payload['ok'] ?? false);
        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            self::fail('Ответ уведомлений должен содержать data.');
        }
        self::assertSame(1, (int)($data['total'] ?? 0));
        self::assertIsArray($data['items'] ?? null);

        $guest = $this->boot(false);
        $unauthorized = $guest->handle(Request::create('/api/v1/panel/notifications', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]));
        self::assertSame(401, $unauthorized->getStatusCode());
    }

    public function testDismissRequiresCsrf(): void
    {
        $app = $this->boot();
        $session = $app->session();
        $id = (int)$app->container()->make(NotificationService::class)
            ->send('events', 'Прочитать', 'info')['id'];

        $body = json_encode(['id' => $id], JSON_THROW_ON_ERROR);
        $forbidden = $app->handle(Request::create('/api/v1/panel/notifications/dismiss', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], $body));
        self::assertSame(403, $forbidden->getStatusCode());

        $ok = $app->handle(Request::create('/api/v1/panel/notifications/dismiss', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $session->csrfToken(),
        ], $body));
        self::assertSame(200, $ok->getStatusCode());
        self::assertSame(0, $app->container()->make(NotificationService::class)->summary()['total']);
    }

    public function testNotifyWrapperAndBellMarkup(): void
    {
        $app = $this->boot();

        $result = notify('Проверка обёртки', 'success');
        self::assertTrue($result['status']);
        $listing = $app->container()->make(NotificationService::class)->listing();
        self::assertSame('system', $listing['items'][0]['module']);
        self::assertSame('Система', $listing['items'][0]['module_title']);
        self::assertSame('/admin', $listing['items'][0]['module_url']);

        $page = $app->handle(Request::create('/admin', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        $html = (string)$page->getContent();
        self::assertStringContainsString('name="csrf-token"', $html);
        self::assertStringContainsString('data-md-notifications-open', $html);
        self::assertStringContainsString('id="mdNotificationsContent"', $html);
        self::assertStringContainsString('id="mdAdminDrawerHost"', $html);
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
