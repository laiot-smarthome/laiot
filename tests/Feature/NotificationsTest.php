<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use MajorDoMo\Events\SystemEvents;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Notifications\NotificationService;
use MajorDoMo\Notifications\SayService;
use MajorDoMo\Notifications\SaySource;
use MajorDoMo\Support\AppContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Центр уведомлений: сервис, окно повторов, helpers, API и CSRF.
 *
 * 2026-10-29 — проверяются важность, порог доставки, повышение и автоматический источник.
 * 2026-10-29 — проверяются временное окно, прочтение, конфиг и контракт say.
 * 2026-10-26 — HTTP-проверки используют действующего пользователя SQLite.
 */
final class NotificationsTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        AppContext::reset();
    }

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
        self::assertSame(5, $item['importance']);
        self::assertSame('Цепочка события', $item['title']);
        self::assertSame('/events', $item['url']);
        self::assertSame('Открыть журнал', $item['label']);
    }

    public function testDuplicateKeyAndNoUnreadLimit(): void
    {
        $app = $this->boot();
        $service = $app->container()->make(NotificationService::class);

        $first = $service->send('events', 'Первое', 'info', ['key' => 'same']);
        $second = $service->send('events', 'Другое сообщение', 'info', ['key' => 'same']);
        self::assertTrue($first['status']);
        self::assertFalse($second['status']);
        self::assertSame((int)$first['id'], (int)($second['id'] ?? 0));

        for ($i = 1; $i <= 11; $i++) {
            self::assertTrue($service->send('events', 'Сообщение ' . $i, 'info')['status']);
        }
        self::assertSame(12, $service->summary()['total']);
    }

    public function testWindowStartsAtLastSavedNotificationAndIgnoresReadStatus(): void
    {
        $app = $this->boot();
        $service = $app->container()->make(NotificationService::class);
        $start = CarbonImmutable::parse('2026-10-29 12:00:00', 'Europe/Moscow');
        CarbonImmutable::setTestNow($start);
        $first = $service->send('events', 'Повтор');
        self::assertTrue($first['status']);

        CarbonImmutable::setTestNow($start->addSeconds(3599));
        self::assertSame(1, $service->dismiss((int)$first['id']));
        $duplicate = $service->send('events', 'Повтор');
        self::assertFalse($duplicate['status']);
        self::assertSame($first['id'], $duplicate['id']);

        CarbonImmutable::setTestNow($start->addHour());
        $next = $service->send('events', 'Повтор');
        self::assertTrue($next['status']);
        self::assertNotSame($first['id'], $next['id']);

        CarbonImmutable::setTestNow($start->addSeconds(3601));
        $duplicate = $service->send('events', 'Повтор');
        self::assertFalse($duplicate['status']);
        self::assertSame($next['id'], $duplicate['id']);
        self::assertSame(2, $app->database()->connection()->table('notifications')->count());
    }

    public function testCustomWindowAppliesToKeysAndUnreadNotifications(): void
    {
        $app = $this->boot(duplicateWindowSeconds: 60);
        $service = $app->container()->make(NotificationService::class);
        $start = CarbonImmutable::parse('2026-10-29 12:00:00', 'Europe/Moscow');
        CarbonImmutable::setTestNow($start);
        $first = $service->send('mqtt', 'Отключён', 'warning', ['key' => 'connection']);

        CarbonImmutable::setTestNow($start->addSeconds(59));
        $duplicate = $service->send('mqtt', 'Всё ещё отключён', 'danger', ['key' => 'connection']);
        self::assertFalse($duplicate['status']);
        self::assertSame($first['id'], $duplicate['id']);
        self::assertTrue($service->send('zigbee', 'Отключён', 'warning', ['key' => 'connection'])['status']);

        CarbonImmutable::setTestNow($start->addMinute());
        self::assertTrue($service->send('mqtt', 'Отключён', 'warning', ['key' => 'connection'])['status']);
        self::assertSame(3, $service->summary()['total']);
    }

    public function testZeroWindowAllowsEveryRepeat(): void
    {
        $app = $this->boot(duplicateWindowSeconds: 0);
        $service = $app->container()->make(NotificationService::class);
        for ($i = 0; $i < 2; $i++) {
            self::assertTrue(say('Повтор')['status']);
            self::assertTrue($service->send('mqtt', 'Отключён', 'warning', ['key' => 'connection'])['status']);
        }
        self::assertSame(4, $service->summary()['total']);
    }

    public function testSayReturnsStoredIdAndUsesSystemSourceByDefault(): void
    {
        $app = $this->boot();
        $first = say('  <b>Проверка</b>  ');
        self::assertTrue($first['status']);
        $query = $app->database()->connection()->table('notifications');
        $row = (clone $query)->find($first['id']);
        self::assertNotNull($row);
        self::assertSame('system', $row->module);
        self::assertSame(5, $row->importance);
        self::assertSame('info', $row->type);
        self::assertSame('Проверка', $row->message);

        $duplicate = say('Проверка');
        self::assertFalse($duplicate['status']);
        self::assertSame($first['id'], $duplicate['id']);
        self::assertTrue(notify('Отдельное уведомление')['status']);
        self::assertTrue($app->container()->make(NotificationService::class)->send('system', 'Проверка', 'warning')['status']);
    }

    public function testSayReportsInvalidInputAndMissingContext(): void
    {
        $app = $this->boot();
        foreach ([['<b> </b>', 5], [str_repeat('я', 256), 5], ['Проверка', -1], ['Проверка', 11]] as [$message, $importance]) {
            $result = say($message, $importance);
            self::assertFalse($result['status']);
            self::assertNotEmpty($result['error']);
        }
        self::assertSame(0, $app->database()->connection()->table('notifications')->count());
        $app->database()->schema()->drop('notifications');
        self::assertFalse(say('Нет таблицы')['status']);
        AppContext::reset();
        self::assertSame(['status' => false, 'error' => 'Приложение не загружено.'], say('Нет контекста'));
    }

    public function testSayQueuesDeliveryWithoutRunningSubscribersOrRepeatingDuplicates(): void
    {
        $app = $this->boot(duplicateWindowSeconds: 60);
        $received = [];
        $app->container()->make(SystemEvents::class)->subscribe('telegram', SayService::EVENT, static function (string $event, array $data) use (&$received): void {
            $received[] = [$event, $data];
        });
        $start = CarbonImmutable::parse('2026-10-29 12:00:00', 'Europe/Moscow');
        CarbonImmutable::setTestNow($start);
        $sent = say('  <b>Дверь открыта</b>  ');
        self::assertTrue($sent['status']);
        self::assertSame([], $received);
        self::assertFalse(say('Дверь открыта')['status']);
        self::assertTrue(notify('Обычное уведомление')['status']);
        self::assertCount(1, $app->queue()->pending('module.telegram'));

        $job = $app->queue()->pop('module.telegram');
        self::assertNotNull($job);
        self::assertSame('module.system.event', $job->job);
        $app->jobs()->handle($job->job, $job->payload);
        $app->queue()->ack($job);
        self::assertSame([[SayService::EVENT, [
            'id' => $sent['id'], 'source' => 'system', 'message' => 'Дверь открыта',
            'type' => 'info', 'importance' => 5, 'created_at' => '2026-10-29 12:00:00',
        ]]], $received);

        CarbonImmutable::setTestNow($start->addMinute());
        self::assertTrue(say('Дверь открыта')['status']);
        self::assertCount(1, $app->queue()->pending('module.telegram'));
    }

    public function testSayRollsBackNotificationAndQueuedJobsOnDispatchFailure(): void
    {
        $app = $this->boot();
        $connection = $app->database()->connection();
        $connection->table('scripts')->insert(['title' => 'Получатель', 'event_name' => SayService::EVENT]);
        $app->queue()->pending('default');
        $app->container()->make(SystemEvents::class)->observe(static function (): void {
            throw new RuntimeException('Ошибка постановки доставки');
        });

        $result = say('Проверка отката');
        self::assertFalse($result['status']);
        self::assertStringContainsString('Ошибка постановки доставки', $result['error']);
        self::assertSame(0, $connection->table('notifications')->count());
        self::assertSame(0, $connection->table('queue_jobs')->count());
    }

    public function testLowImportanceIsStoredWithoutDeliveryAndEscalationBypassesWindow(): void
    {
        $app = $this->boot();
        $app->container()->make(SystemEvents::class)->subscribe('telegram', SayService::EVENT, static function (): void {
        });
        $app->queue()->pending('module.telegram');
        $first = say('Температура растёт', 4);
        self::assertTrue($first['status']);
        self::assertSame([], $app->queue()->pending('module.telegram'));
        self::assertSame(4, $app->database()->connection()->table('notifications')->find($first['id'])->importance);
        self::assertFalse(say('Температура растёт', 4)['status']);

        $raised = say('Температура растёт', 10);
        self::assertTrue($raised['status']);
        self::assertNotSame($first['id'], $raised['id']);
        self::assertCount(1, $app->queue()->pending('module.telegram'));
        foreach ([10, 4, 9] as $importance) {
            $duplicate = say('Температура растёт', $importance);
            self::assertFalse($duplicate['status']);
            self::assertSame($raised['id'], $duplicate['id']);
        }
        self::assertSame(2, $app->database()->connection()->table('notifications')->count());

        self::assertTrue(say('Порог включительно', 5)['status']);
        self::assertCount(2, $app->queue()->pending('module.telegram'));
        $payloads = $app->database()->connection()->table('queue_jobs')->orderBy('id')->pluck('payload');
        self::assertSame(10, json_decode($payloads[0], true, 512, JSON_THROW_ON_ERROR)['data']['importance']);
    }

    public function testConfigurableThresholdIncludesZeroAndTen(): void
    {
        foreach ([0, 10] as $minimum) {
            $app = $this->boot(minimumImportance: $minimum);
            $app->container()->make(SystemEvents::class)->subscribe('telegram', SayService::EVENT, static function (): void {
            });
            $app->queue()->pending('module.telegram');
            foreach ([0, 9, 10] as $importance) {
                self::assertTrue(say('Уровень ' . $importance, $importance)['status']);
            }
            self::assertCount($minimum === 0 ? 3 : 1, $app->queue()->pending('module.telegram'));
            self::assertSame(3, $app->database()->connection()->table('notifications')->count());
        }
    }

    public function testModuleSourcesAreDetectedForCoreAndThirdPartyCode(): void
    {
        foreach (['core/mqtt' => 'mqtt', 'my_sensor' => 'my_sensor'] as $relative => $source) {
            $app = $this->boot();
            $root = sys_get_temp_dir() . '/mydomo-say-' . bin2hex(random_bytes(8));
            $directory = $root . '/modules/' . $relative;
            mkdir($directory, 0700, true);
            $file = $directory . '/helpers.php';
            file_put_contents($file, '<?php return static fn (): array => say("От модуля", 7);');
            try {
                $app->container()->instance(SaySource::class, new SaySource($root));
                $call = require $file;
                $result = $call();
                self::assertTrue($result['status']);
                $row = $app->database()->connection()->table('notifications')->find($result['id']);
                self::assertSame($source, $row->module);
                self::assertSame(7, $row->importance);
                self::assertTrue(say('От ядра')['status']);
                self::assertSame('system', $app->database()->connection()->table('notifications')->orderByDesc('id')->first()->module);
            } finally {
                unlink($file);
                rmdir($directory);
                if (str_starts_with($relative, 'core/')) {
                    rmdir($root . '/modules/core');
                }
                rmdir($root . '/modules');
                rmdir($root);
            }
        }
    }

    public function testImportanceMigrationPreservesExistingRowsAndIsRepeatable(): void
    {
        $app = $this->boot();
        $connection = $app->database()->connection();
        $app->database()->schema()->table('notifications', static function (Blueprint $table): void {
            $table->dropColumn('importance');
        });
        $id = $connection->table('notifications')->insertGetId(['module' => 'system', 'message' => 'Прежняя запись']);
        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_10_29_000001_add_notification_importance.php';
        $migration($connection);
        $migration($connection);
        self::assertSame(5, $connection->table('notifications')->find($id)->importance);
        self::assertTrue(say('После миграции', 10)['status']);
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

    private function boot(bool $login = true, ?int $duplicateWindowSeconds = null, ?int $minimumImportance = null): Application
    {
        $session = new ArraySession();
        if ($login) {
            $session->login(['id' => 1, 'username' => 'admin', 'name' => 'Администратор', 'is_admin' => true]);
        }

        $config = [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $session,
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ];
        if ($duplicateWindowSeconds !== null) {
            $config['notifications'] = ['duplicate_window' => $duplicateWindowSeconds];
        }
        if ($minimumImportance !== null) {
            $config['notifications']['minimum_importance'] = $minimumImportance;
        }
        $app = new Application(dirname(__DIR__, 2), $config);
        $app->boot();
        $app->database()->schema()->create('scripts', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->string('event_name')->default('');
            $table->softDeletes();
        });
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

        return $app;
    }
}
