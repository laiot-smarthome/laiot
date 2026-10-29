<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use LaIOT\Services\Events\SystemEvents;
use LaIOT\Services\Properties\PropertyValueService;
use LaIOT\Services\WebSocket\WebSocketChannels;
use LaIOT\Services\WebSocket\WebSocketConfig;
use LaIOT\Services\WebSocket\WebSocketTicketRepository;
use LaIOT\Services\WebSocket\WebSocketTicketService;
use PHPUnit\Framework\TestCase;

/**
 * WebSocket-тикет, снимок и outbox изменений свойств.
 *
 * 2026-10-29 — проверки авторизации, формата каналов и публикации.
 */
final class WebSocketTest extends TestCase
{
    private Application $app;
    private ArraySession $session;

    protected function setUp(): void
    {
        $this->session = new ArraySession();
        $this->session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'log' => 'php://stderr',
            'session' => $this->session,
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'websocket' => ['enabled' => true, 'workers' => 1],
        ]);
        $this->app->boot();

        foreach ([
            '2026_10_26_000001_create_queue_jobs.php',
            '2026_10_26_000008_create_users.php',
            '2026_10_26_000013_create_classes.php',
            '2026_10_26_000014_create_properties.php',
            '2026_10_26_000015_create_methods.php',
            '2026_10_26_000016_create_objects_and_values.php',
            '2026_10_26_000017_create_property_history.php',
            '2026_10_26_000019_create_property_events.php',
            '2026_10_27_000001_add_class_hierarchy_and_object_members.php',
            '2026_10_27_000002_create_property_subscriptions.php',
            '2026_10_27_000009_add_class_description.php',
            '2026_10_27_000010_add_property_only_new_values.php',
            '2026_10_27_000011_create_property_executions.php',
            '2026_10_29_000002_create_websocket_tables.php',
        ] as $name) {
            $migration = require dirname(__DIR__, 2) . '/database/migrations/' . $name;
            $migration($this->app->database()->connection());
        }

        SessionFixture::authenticate($this->app);
        $now = date('Y-m-d H:i:s');
        $this->app->database()->connection()->table('classes')->insert([
            'title' => 'Sensor', 'parent_id' => 0, 'description' => '',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->app->database()->connection()->table('objects')->insert([
            'title' => 'Kitchen', 'class_id' => 1, 'location_id' => 0, 'description' => '',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->app->database()->connection()->table('properties')->insert([
            'class_id' => 1, 'object_id' => 0, 'title' => 'temperature', 'description' => '',
            'data_type' => 'float', 'keep_history' => 0, 'only_new_values' => 0, 'on_change' => '',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->app->database()->connection()->table('property_values')->insert([
            'object_id' => 1, 'property_id' => 1, 'value' => '21.5',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function testTicketEndpointReturnsScopedSnapshotAndStoresOnlyHash(): void
    {
        $response = $this->app->handle(Request::create('/api/v1/panel/websocket/ticket', 'POST', [
            '_token' => $this->session->csrfToken(),
            'channels' => ['Kitchen.temperature', 'Kitchen.temperature'],
        ]));
        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string)$response->getContent(), true);
        self::assertIsArray($payload);
        self::assertTrue($payload['ok']);
        $ticket = $payload['data']['ticket'];
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $ticket);
        self::assertSame([['object' => 'Kitchen', 'property' => 'temperature', 'value' => '21.5']], $payload['data']['values']);
        self::assertSame(0, $payload['data']['cursor']);
        self::assertSame('laiot.v1', $payload['data']['protocol']);

        $row = $this->app->database()->connection()->table('websocket_tickets')->first();
        self::assertNotNull($row);
        self::assertSame(hash('sha256', $ticket), (string)$row->ticket_hash);
        self::assertStringNotContainsString($ticket, (string)$row->ticket_hash);

        $resolved = $this->app->container()->make(WebSocketTicketService::class)->authorize($ticket);
        self::assertNotNull($resolved);
        self::assertSame(1, $resolved['user_id']);
        self::assertSame(['Kitchen.temperature', 'system'], $resolved['channels']);
    }

    public function testTicketEndpointAcceptsJsonBodyFromPanelClient(): void
    {
        $request = Request::create(
            '/api/v1/panel/websocket/ticket',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $this->session->csrfToken(),
            ],
            (string)json_encode(['channels' => ['Kitchen.temperature']])
        );
        $response = $this->app->handle($request);
        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string)$response->getContent(), true);
        self::assertIsArray($payload);
        self::assertTrue($payload['ok']);
    }

    public function testTicketRequiresSessionAndValidChannels(): void
    {
        $this->session->logout();
        $unauthorized = $this->app->handle(Request::create('/api/v1/panel/websocket/ticket', 'POST', [
            'channels' => ['Kitchen.temperature'],
        ]));
        self::assertSame(401, $unauthorized->getStatusCode());

        SessionFixture::authenticate($this->app);
        $this->session->login(['id' => 1, 'username' => 'admin', 'name' => 'Admin', 'is_admin' => true]);
        SessionFixture::authenticate($this->app);
        $invalid = $this->app->handle(Request::create('/api/v1/panel/websocket/ticket', 'POST', [
            '_token' => $this->session->csrfToken(),
            'channels' => ['not-a-channel'],
        ]));
        self::assertSame(422, $invalid->getStatusCode());
        self::assertSame(0, $this->app->database()->connection()->table('websocket_tickets')->count());
    }

    public function testExpiredAndRevokedTicketsAreRejected(): void
    {
        $service = $this->app->container()->make(WebSocketTicketService::class);
        $issued = $service->issue($this->session->user() ?? [], ['Kitchen.temperature']);
        self::assertNotNull($service->authorize($issued['ticket']));

        $this->app->database()->connection()->table('websocket_tickets')
            ->update(['expires_at' => date('Y-m-d H:i:s', time() - 1)]);
        self::assertNull($service->authorize($issued['ticket']));

        $fresh = $service->issue($this->session->user() ?? [], ['Kitchen.temperature']);
        $this->app->container()->make(WebSocketTicketRepository::class)->revokeForUser(1);
        self::assertNull($service->authorize($fresh['ticket']));
    }

    public function testPropertyWritePublishesOnlyActualChanges(): void
    {
        $service = $this->app->container()->make(PropertyValueService::class);
        self::assertTrue($service->setValue(1, 1, '22.1', 'test'));
        self::assertSame(1, $this->app->database()->connection()->table('websocket_events')->count());
        $event = $this->app->database()->connection()->table('websocket_events')->first();
        self::assertNotNull($event);
        self::assertSame('property', (string)$event->type);
        self::assertSame('Kitchen', (string)$event->object_title);
        self::assertSame('temperature', (string)$event->property_title);
        self::assertSame('21.5', (string)$event->old_value);
        self::assertSame('22.1', (string)$event->value);

        // Повтор того же значения не создаёт событие: показывать нечего.
        self::assertTrue($service->setValue(1, 1, '22.1', 'test'));
        self::assertSame(1, $this->app->database()->connection()->table('websocket_events')->count());
    }

    public function testSystemEventIsWrittenToSameOutbox(): void
    {
        $this->app->database()->schema()->create('scripts', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title', 191);
            $table->text('code')->nullable();
            $table->string('event_name', 191)->default('');
            $table->timestamps();
            $table->softDeletes();
        });
        $this->app->container()->make(SystemEvents::class)->emit('system.minute', [
            'occurred_at' => '2026-10-29T15:00:00+03:00',
        ]);
        $event = $this->app->database()->connection()->table('websocket_events')->first();
        self::assertNotNull($event);
        self::assertSame('system', (string)$event->type);
        self::assertSame('system.minute', (string)$event->event_name);
        self::assertSame('{"occurred_at":"2026-10-29T15:00:00+03:00"}', (string)$event->payload);
    }

    public function testChannelsAreNormalizedAndLimited(): void
    {
        self::assertSame(
            ['Kitchen.temperature', 'Light.status', 'system'],
            WebSocketChannels::normalize(['Kitchen.temperature', 'Kitchen.temperature', 'Light.status'], 10)
        );
        $this->expectException(InvalidArgumentException::class);
        WebSocketChannels::normalize(['Kitchen.temperature', 'Light.status'], 1);
    }

    public function testConfigNormalizesEnvironmentValues(): void
    {
        $config = WebSocketConfig::fromArray([
            'enabled' => '0',
            'port' => 0,
            'allowed_origins' => 'https://home.example, https://panel.example/',
        ]);
        self::assertFalse($config->enabled);
        self::assertSame(8081, $config->port);
        self::assertTrue($config->originAllowed('https://home.example'));
        self::assertTrue($config->originAllowed('https://panel.example'));
        self::assertFalse($config->originAllowed('https://evil.example'));
    }

    public function testOriginAllowsRootDomainAndSubdomainsByDefault(): void
    {
        $config = WebSocketConfig::fromArray(['base_url' => 'https://example.com']);
        self::assertSame('example.com', $config->rootDomain);
        self::assertTrue($config->originAllowed('https://example.com'));
        self::assertTrue($config->originAllowed('https://home.example.com'));
        self::assertTrue($config->originAllowed('https://a.b.example.com:8443'));
        self::assertFalse($config->originAllowed('https://example.com.evil'));
        self::assertFalse($config->originAllowed('https://notexample.com'));
        self::assertFalse($config->originAllowed('ftp://example.com'));

        $fromRequest = WebSocketConfig::fromArray([]);
        self::assertTrue($fromRequest->originAllowed('https://app.example.com', 'app.example.com'));
        self::assertTrue($fromRequest->originAllowed('https://sub.app.example.com', 'app.example.com'));
        self::assertFalse($fromRequest->originAllowed('https://other.example.com', 'app.example.com'));
    }
}
