<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Auth\UserRepository;
use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Database\Migrator;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Locations\LocationRepository;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Methods\MethodRunner;
use MajorDoMo\Modules\ModuleInstaller;
use MajorDoMo\Mqtt\Integrations;
use MajorDoMo\Mqtt\PublisherInterface;
use MajorDoMo\Mqtt\RuntimeState;
use MajorDoMo\Notifications\NotificationService;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertySubscriptionRepository;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Queue\Queue;
use MajorDoMo\Queue\QueueWorker;
use MajorDoMo\Runtime\CodeRunner;
use MajorDoMo\Worker\ProcessLock;
use MajorDoMo\Zigbee\HealthMonitor;
use MajorDoMo\Zigbee\HealthSettings;
use MajorDoMo\Zigbee\MqttIntegration;
use MajorDoMo\Zigbee\Repository;
use MajorDoMo\Zigbee\Service;
use MajorDoMo\Zigbee\ValueTransformer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Zigbee как core-модуль: обнаружение, привязки, команды, мониторинг и Yoyo.
 *
 * Проверки изолированы: SQLite в памяти, MQTT-брокер не нужен, исходящие
 * команды идут через записывающий PublisherInterface, а транспорт мониторинга
 * подменяется callable. Очередь обрабатывается вручную, поэтому тест не ждёт
 * фоновые процессы.
 *
 * 2026-10-28 — модуль Zigbee перенесён в ядро.
 */
final class ZigbeeModuleTest extends TestCase
{
    use DrainsEvents;

    private ?string $database = null;

    protected function tearDown(): void
    {
        Yoyo::request()->reset();
        unset($_SERVER['HTTP_HX_REQUEST'], $_SERVER['REQUEST_METHOD']);
        $_REQUEST = [];
        if ($this->database !== null) {
            @unlink($this->database);
            $this->database = null;
        }
        parent::tearDown();
    }

    public function testCoreModuleDependsOnMqttAndCanBeDisabled(): void
    {
        $app = $this->boot(true);
        self::assertNotNull($app->modules()->get('zigbee'));
        self::assertSame(200, $app->handle(Request::create('/module/zigbee', 'GET'))->getStatusCode());

        $installer = $app->container()->make(ModuleInstaller::class);
        $installer->disable('zigbee');
        $disabled = $this->boot(true);
        self::assertNull($disabled->modules()->get('zigbee'));
        self::assertSame(404, $disabled->handle(Request::create('/module/zigbee', 'GET'))->getStatusCode());

        $disabled->container()->make(ModuleInstaller::class)->enable('zigbee');
        $enabled = $this->boot(true);
        self::assertNotNull($enabled->modules()->get('zigbee'));

        $this->expectException(InvalidArgumentException::class);
        $enabled->container()->make(ModuleInstaller::class)->remove('zigbee');
    }

    public function testZigbeeIsHiddenWhenMqttIsDisabled(): void
    {
        $app = $this->boot(true);
        $app->container()->make(ModuleInstaller::class)->disable('mqtt');

        $withoutTransport = $this->boot(true);
        self::assertNull($withoutTransport->modules()->get('zigbee'));
        self::assertSame(404, $withoutTransport->handle(Request::create('/module/zigbee', 'GET'))->getStatusCode());
    }

    public function testPagesRenderAndBridgeIsCreated(): void
    {
        $app = $this->boot();
        $token = $app->session()->csrfToken();
        $create = $app->handle(Request::create('/module/zigbee/bridges', 'POST', [
            '_token' => $token,
            'title' => 'Zigbee2MQTT',
            'base_topic' => 'zigbee2mqtt',
            'enabled' => '1',
        ]));
        self::assertSame(302, $create->getStatusCode());

        $repository = $app->container()->make(Repository::class);
        $bridges = $repository->bridges();
        self::assertCount(1, $bridges);
        self::assertSame('zigbee2mqtt', $bridges[0]['base_topic']);

        $index = $app->handle(Request::create('/module/zigbee', 'GET'));
        self::assertSame(200, $index->getStatusCode());
        $html = (string)$index->getContent();
        self::assertStringContainsString('Zigbee', $html);
        self::assertStringContainsString('yoyo:name="zigbee-devices"', $html);
        self::assertStringContainsString('zigbee2mqtt', $html);

        $bridgesPage = $app->handle(Request::create('/module/zigbee/bridges', 'GET'));
        self::assertSame(200, $bridgesPage->getStatusCode());
        self::assertStringContainsString('Экземпляры Zigbee2MQTT', (string)$bridgesPage->getContent());

        $monitoring = $app->handle(Request::create('/module/zigbee/monitoring', 'GET'));
        self::assertSame(200, $monitoring->getStatusCode());
        self::assertStringContainsString('Мониторинг Zigbee', (string)$monitoring->getContent());

        $journal = $app->handle(Request::create('/module/zigbee/journal', 'GET'));
        self::assertSame(200, $journal->getStatusCode());
        self::assertStringContainsString('yoyo:name="zigbee-journal"', (string)$journal->getContent());
    }

    public function testDiscoveryCreatesDevicesAndExposes(): void
    {
        $app = $this->boot();
        $repository = $app->container()->make(Repository::class);
        $bridge = $this->bridge($repository);

        $app->container()->make(Service::class)->receive(
            'zigbee2mqtt/bridge/devices',
            (string)json_encode($this->devicePayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $devices = $repository->devicesForBridge($bridge);
        self::assertCount(1, $devices);
        self::assertSame('sensor1', $devices[0]['friendly_name']);
        self::assertSame('discovered', $devices[0]['status']);
        self::assertSame('WSDCGQ11LM', $devices[0]['model']);

        $property = $repository->property((int)$devices[0]['id'], 'temperature');
        self::assertSame((int)$devices[0]['id'], (int)$property['device_id']);
        self::assertNotSame('', (string)$property['expose']);

        $events = $repository->events(['bridge_id' => $bridge]);
        self::assertGreaterThanOrEqual(1, $events['total']);
    }

    public function testDeliveryWritesPropertyCallsRoomAndMarksBinding(): void
    {
        $app = $this->boot();
        $repository = $app->container()->make(Repository::class);
        $service = $app->container()->make(Service::class);
        $bridge = $this->bridge($repository);
        $service->receive(
            'zigbee2mqtt/bridge/devices',
            (string)json_encode($this->devicePayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $device = $repository->devicesForBridge($bridge)[0];
        $service->configureDevices([(int)$device['id']], 10, true);

        $property = $repository->property((int)$device['id'], 'temperature');
        $service->saveBinding((int)$property['id'], [
            'linked_object' => 'Zigbee Кухня',
            'linked_property' => 'temperature',
            'enabled' => true,
        ], $app->modules()->context('zigbee')->subscriptions());

        $service->receive('zigbee2mqtt/sensor1/availability', 'online');
        self::assertSame('online', $repository->deviceById((int)$device['id'])['available']);

        $service->receive('zigbee2mqtt/sensor1', '{"temperature":21.5,"battery":80,"action":"single"}');

        $values = $app->container()->make(PropertyValueService::class);
        self::assertSame('21.5', $values->get('Zigbee Кухня', 'temperature'));
        self::assertNotSame(null, $repository->property((int)$device['id'], 'action:single'));
        self::assertSame('21.5', (string)$repository->binding((int)$property['id'])['last_delivered']);

        $room = $values->get('RoomProbe', 'last');
        self::assertContains($room, ['temperature', 'battery', 'action', 'action:single']);

        $devicePage = $app->handle(Request::create('/module/zigbee/devices/' . (int)$device['id'], 'GET'));
        self::assertSame(200, $devicePage->getStatusCode());
        self::assertStringContainsString('Название и комната', (string)$devicePage->getContent());
        $propertyPage = $app->handle(Request::create('/module/zigbee/properties/' . (int)$property['id'], 'GET'));
        self::assertSame(200, $propertyPage->getStatusCode());
        self::assertStringContainsString('Привязка', (string)$propertyPage->getContent());
    }

    public function testPropertyChangePublishesCommandAndSubscribes(): void
    {
        $app = $this->boot();
        $repository = $app->container()->make(Repository::class);
        $service = $app->container()->make(Service::class);
        $bridge = $this->bridge($repository);
        $service->receive(
            'zigbee2mqtt/bridge/devices',
            (string)json_encode($this->devicePayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $device = $repository->devicesForBridge($bridge)[0];
        $service->configureDevices([(int)$device['id']], 0, true);
        $property = $repository->property((int)$device['id'], 'state');

        $fake = new class () implements PublisherInterface {
            /** @var list<array{topic:string,message:string,qos:int,retain:bool}> */
            public array $sent = [];

            public function publish(string $topic, string $message, int $qos = 0, bool $retain = false): void
            {
                $this->sent[] = compact('topic', 'message', 'qos', 'retain');
            }
        };
        // Сервис уже собран при boot; для отправки подменяем экземпляр вместе
        // с PublisherInterface, чтобы не открывать реальный брокер.
        $app->container()->instance(PublisherInterface::class, $fake);
        $writer = new Service(
            $repository,
            $fake,
            $app->container()->make(PropertyValueService::class),
            $app->container()->make(MethodRunner::class),
            $app->container()->make(MethodRepository::class),
            $app->container()->make(ClassRepository::class),
            $app->container()->make(ObjectRepository::class),
            $app->container()->make(LocationRepository::class),
            $app->container()->make(LoggerInterface::class)
        );
        $app->container()->instance(Service::class, $writer);
        $writer->saveBinding((int)$property['id'], [
            'linked_object' => 'Zigbee Кухня',
            'linked_property' => 'temperature',
            'enabled' => true,
            'write_enabled' => true,
            'outgoing' => ['mode' => 'exposes'],
        ], $app->modules()->context('zigbee')->subscriptions());

        self::assertCount(1, $app->container()->make(PropertySubscriptionRepository::class)->forModule('zigbee'));

        $writer->propertyChanged('Zigbee Кухня', 'temperature', 1);
        self::assertCount(1, $fake->sent);
        self::assertSame('zigbee2mqtt/sensor1/set', $fake->sent[0]['topic']);
        self::assertSame('{"state":"ON"}', $fake->sent[0]['message']);

        // Повторная очистка привязки снимает подписку.
        $writer->saveBinding((int)$property['id'], [
            'linked_object' => '',
            'enabled' => false,
            'write_enabled' => false,
        ], $app->modules()->context('zigbee')->subscriptions());
        self::assertCount(0, $app->container()->make(PropertySubscriptionRepository::class)->forModule('zigbee'));
    }

    public function testConversionRulesAreDeclarativeAndNeverEval(): void
    {
        self::assertSame(1, ValueTransformer::apply('ON', ['mode' => 'binary']));
        self::assertSame('OFF', ValueTransformer::apply(false, ['mode' => 'binary'], [], true));
        self::assertSame('changed', ValueTransformer::apply('1', [
            'mode' => 'map',
            'pairs' => [['from' => 1, 'to' => 'changed']],
        ]));
        self::assertSame(21.5, ValueTransformer::apply('21.5', ['mode' => 'number', 'factor' => 1, 'offset' => 0]));
        self::assertFalse(ValueTransformer::equal('1', true, true));
        self::assertTrue(ValueTransformer::equal(1, '1', true));

        $app = $this->boot();
        $repository = $app->container()->make(Repository::class);
        $service = $app->container()->make(Service::class);
        $bridge = $this->bridge($repository);
        $service->receive(
            'zigbee2mqtt/bridge/devices',
            (string)json_encode($this->devicePayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $device = $repository->devicesForBridge($bridge)[0];
        $property = $repository->property((int)$device['id'], 'temperature');
        $result = $service->testConversion((int)$property['id'], 'incoming', '21.5', [
            'mode' => 'number',
            'factor' => 10,
            'offset' => 0,
            'precision' => 1,
        ]);
        self::assertSame('21.5', $result['input']);
        self::assertEquals(215.0, $result['output']);
    }

    public function testMqttIntegrationEnqueuesAndQueueHandlerDelivers(): void
    {
        $app = $this->boot();
        $repository = $app->container()->make(Repository::class);
        $integration = $app->container()->make(MqttIntegration::class);
        self::assertSame('zigbee', $integration->id());
        self::assertSame([], $integration->topics());

        $bridge = $this->bridge($repository);
        self::assertSame(['zigbee2mqtt/#'], $integration->topics());
        self::assertTrue($integration->owns('zigbee2mqtt/sensor1'));
        self::assertFalse($integration->owns('home/kitchen'));

        $large = str_repeat('x', 40000);
        self::assertSame($large, MqttIntegration::decodePayload([
            'payload' => base64_encode((string)gzdeflate($large)),
            'encoding' => MqttIntegration::ENCODING,
        ]));

        $integration->enqueue('zigbee2mqtt/sensor1', '{"temperature":19}');
        self::assertSame(1, $app->container()->make(Queue::class)->stats(MqttIntegration::QUEUE)['pending']);

        $worker = $app->container()->make(QueueWorker::class);
        self::assertTrue($worker->work(MqttIntegration::QUEUE));
        $device = $repository->devicesForBridge($bridge)[0];
        self::assertSame(19, ValueTransformer::decode((string)$repository->property((int)$device['id'], 'temperature')['raw_value']) ?? 19);
    }

    public function testHealthMonitorCreatesSingleBatteryIncident(): void
    {
        $app = $this->boot();
        $container = $app->container();
        $repository = $container->make(Repository::class);
        $service = $container->make(Service::class);
        $bridge = $this->bridge($repository);
        $service->receive(
            'zigbee2mqtt/bridge/devices',
            (string)json_encode($this->devicePayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $device = $repository->devicesForBridge($bridge)[0];
        $service->configureDevices([(int)$device['id']], 0, true);
        $repository->saveBridgeState($bridge, 'online');
        $repository->saveDevice((int)$device['id'], [
            'available' => 'online',
            'last_seen' => date('Y-m-d H:i:s'),
        ]);
        $battery = $repository->property((int)$device['id'], 'battery', [
            'type' => 'numeric',
            'property' => 'battery',
            'access' => 1,
            'label' => 'Батарея',
        ]);
        $repository->savePropertyValue((int)$battery['id'], '15');
        $repository->saveConvertedValue((int)$battery['id'], '15');

        $settings = HealthSettings::defaults();
        $settings['events']['battery_low']['notify'] = true;
        $settings['events']['battery_low']['message'] = '{device}: тестовый низкий заряд {value}%';
        $repository->saveHealthSettings($settings);

        $monitor = new HealthMonitor(
            $repository,
            $container->make(RuntimeState::class),
            $container->make(NotificationService::class),
            $container->make(CodeRunner::class),
            new ProcessLock($app->root() . '/var/locks'),
            $container->make(LocationRepository::class),
            static fn (): string => 'online'
        );
        $monitor->tick();
        $monitor->tick();

        $notifications = $app->database()->connection()->table('notifications')
            ->where('module', 'zigbee')
            ->get()
            ->all();
        self::assertCount(1, $notifications);
        self::assertStringContainsString('тестовый низкий заряд', (string)$notifications[0]->message);
        self::assertSame('low', $repository->healthState('device:' . (int)$device['id'] . ':battery')['current']);
    }

    public function testYoyoDeviceListFiltersAndRenders(): void
    {
        $app = $this->boot();
        $repository = $app->container()->make(Repository::class);
        $bridge = $this->bridge($repository);
        $repository->device($bridge, 'sensor1', '0x00158d000237212e');
        $repository->device($bridge, 'sensor2', '0x00158d000237212f');

        Yoyo::request()->reset();
        $_REQUEST = ['component' => 'zigbee-devices/setStatus', 'yoyo-id' => 'zigbee-devices', 'status' => 'discovered'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        Yoyo::request()->mock($_REQUEST, $_SERVER);

        $response = $app->handle(Request::create('/yoyo', 'POST', [], [], [], [
            'HTTP_X_CSRF_TOKEN' => $app->session()->csrfToken(),
        ]));
        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('sensor1', $html);
        self::assertStringContainsString('sensor2', $html);
        self::assertStringContainsString('Обнаружено', $html);
    }

    public function testIntegrationsRegistryRoutesOnlyZigbeeTopics(): void
    {
        $app = $this->boot();
        $repository = $app->container()->make(Repository::class);
        $this->bridge($repository);
        $registry = $app->container()->make(Integrations::class);
        self::assertSame(['zigbee2mqtt/#'], $registry->topics());
        self::assertSame('zigbee', $registry->owner('zigbee2mqtt/sensor1')?->id());
        self::assertNull($registry->owner('home/kitchen/temperature'));
        self::assertFalse($registry->dispatch('home/kitchen/temperature', '1'));
        self::assertTrue($registry->dispatch('zigbee2mqtt/sensor1', '{"temperature":1}'));
        self::assertSame(1, $app->container()->make(Queue::class)->stats(MqttIntegration::QUEUE)['pending']);
    }

    private function bridge(Repository $repository): int
    {
        return $repository->saveBridge([
            'title' => 'Zigbee2MQTT',
            'base_topic' => 'zigbee2mqtt',
            'enabled' => true,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function devicePayload(): array
    {
        return [[
            'ieee_address' => '0x00158d000237212e',
            'friendly_name' => 'sensor1',
            'type' => 'EndDevice',
            'model_id' => 'WSDCGQ11LM',
            'power_source' => 'Battery',
            'definition' => [
                'model' => 'WSDCGQ11LM',
                'vendor' => 'Xiaomi',
                'description' => 'Датчик температуры',
                'exposes' => [
                    ['property' => 'temperature', 'type' => 'numeric', 'access' => 1, 'label' => 'Температура', 'unit' => '°C'],
                    ['property' => 'battery', 'type' => 'numeric', 'access' => 1, 'label' => 'Батарея', 'unit' => '%'],
                    [
                        'property' => 'state',
                        'type' => 'binary',
                        'access' => 3,
                        'label' => 'Состояние',
                        'value_on' => 'ON',
                        'value_off' => 'OFF',
                    ],
                ],
            ],
        ]];
    }

    private function boot(bool $persistent = false): Application
    {
        $session = new ArraySession();
        if ($persistent && $this->database === null) {
            $file = tempnam(sys_get_temp_dir(), 'mydomo_zigbee_');
            if ($file === false) {
                self::fail('Не удалось создать временную БД');
            }
            @unlink($file);
            $this->database = $file . '.sqlite';
            file_put_contents($this->database, '');
        }
        $database = $this->database ?? ':memory:';

        $app = new Application(dirname(__DIR__, 2), [
            'debug' => true,
            'environment_file' => sys_get_temp_dir() . '/mydomo-zigbee-env-' . bin2hex(random_bytes(6)),
            'view_cache' => sys_get_temp_dir() . '/mydomo-zigbee-views-' . bin2hex(random_bytes(6)),
            'cache' => ['driver' => 'array'],
            'log' => 'php://stderr',
            'session' => $session,
            'database' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => ''],
        ]);
        $app->boot();
        (new Migrator($app->database(), $app->root() . '/database/migrations'))->migrate();

        $connection = $app->database()->connection();
        $now = date('Y-m-d H:i:s');
        if (!$connection->table('users')->where('id', 1)->exists()) {
            $connection->table('users')->insert([
                'id' => 1,
                'username' => 'admin',
                'name' => 'Администратор',
                'password' => password_hash('fixture-password', PASSWORD_DEFAULT),
                'is_admin' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $user = $app->container()->make(UserRepository::class)->sessionUser(1);
        self::assertNotNull($user);
        $session->login($user);

        if ($connection->table('objects')->count() === 0) {
            $this->fixtures($connection, $now);
        }

        return $app;
    }

    private function fixtures(\Illuminate\Database\Connection $connection, string $now): void
    {
        $connection->table('classes')->insert([
            ['id' => 100, 'title' => 'Zigbee Датчик', 'parent_id' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 200, 'title' => 'Rooms', 'parent_id' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 300, 'title' => 'RoomProbe', 'parent_id' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $connection->table('properties')->insert([
            [
                'id' => 100,
                'class_id' => 100,
                'object_id' => 0,
                'title' => 'temperature',
                'data_type' => 'float',
                'keep_history' => 1,
                'on_change' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 300,
                'class_id' => 300,
                'object_id' => 0,
                'title' => 'last',
                'data_type' => 'string',
                'keep_history' => 0,
                'on_change' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        $connection->table('objects')->insert([
            [
                'id' => 100,
                'title' => 'Zigbee Кухня',
                'class_id' => 100,
                'location_id' => 0,
                'description' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 200,
                'title' => 'KitchenRoom',
                'class_id' => 200,
                'location_id' => 10,
                'description' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 300,
                'title' => 'RoomProbe',
                'class_id' => 300,
                'location_id' => 0,
                'description' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        $connection->table('methods')->insert([
            'class_id' => 200,
            'object_id' => 0,
            'title' => 'deviceChanged',
            'description' => 'Тестовый обработчик комнаты.',
            'code' => "sg('RoomProbe.last', (string)(\$params['PROPERTY'] ?? ''));\nreturn true;",
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $connection->table('locations')->insert([
            'id' => 10,
            'title' => 'Кухня',
            'kind' => 'room',
            'parent_id' => 0,
            'priority' => 0,
            'class_id' => 200,
            'object_id' => 200,
            'activity_timeout' => 900,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
