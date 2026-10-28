<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Auth\UserRepository;
use MajorDoMo\Database\Migrator;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Modules\ModuleInstaller;
use MajorDoMo\Mqtt\BindingIndex;
use MajorDoMo\Mqtt\InboundHandler;
use MajorDoMo\Mqtt\PropertyPublisher;
use MajorDoMo\Mqtt\Publisher;
use MajorDoMo\Mqtt\PublisherInterface;
use MajorDoMo\Mqtt\RuntimeState;
use MajorDoMo\Mqtt\Settings;
use MajorDoMo\Mqtt\SocketClient;
use MajorDoMo\Mqtt\TopicRepository;
use MajorDoMo\Mqtt\TopicService;
use MajorDoMo\Properties\PropertySubscriptionRepository;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Worker\Worker;
use PHPUnit\Framework\TestCase;

/**
 * MQTT как core-модуль: страницы, настройки, приём и отправка команд.
 *
 * Проверки изолированы: SQLite, брокер не нужен. Входящее сообщение
 * обрабатывается сервисом напрямую, исходящее — через подменённый
 * PublisherInterface, поэтому тест не зависит от сети.
 *
 * 2026-10-27 — модуль MQTT перенесён в ядро.
 */
final class MqttModuleTest extends TestCase
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

    public function testCoreModuleCanBeDisabledButNotRemoved(): void
    {
        $app = $this->boot(true);
        $installer = $app->container()->make(ModuleInstaller::class);
        self::assertNotNull($app->modules()->get('mqtt'));

        $installer->disable('mqtt');
        $row = $app->database()->connection()->table('module_registry')->where('module_id', 'mqtt')->first();
        self::assertNotNull($row);
        self::assertSame(0, (int)$row->enabled);

        $disabled = $this->boot(true);
        self::assertNull($disabled->modules()->get('mqtt'));
        self::assertSame(404, $disabled->handle(Request::create('/module/mqtt', 'GET'))->getStatusCode());

        $disabled->container()->make(ModuleInstaller::class)->enable('mqtt');
        $enabled = $this->boot(true);
        self::assertNotNull($enabled->modules()->get('mqtt'));
        self::assertSame(200, $enabled->handle(Request::create('/module/mqtt', 'GET'))->getStatusCode());

        $this->expectException(InvalidArgumentException::class);
        $enabled->container()->make(ModuleInstaller::class)->remove('mqtt');
    }

    public function testPageRendersReactiveTopicsAndStatus(): void
    {
        $app = $this->boot();
        $response = $app->handle(Request::create('/module/mqtt', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getContent();
        self::assertStringContainsString('MQTT', $html);
        self::assertStringContainsString('Добавить топик', $html);
        self::assertStringContainsString('yoyo:name="mqtt-topics"', $html);
        self::assertStringContainsString('yoyo:name="mqtt-status"', $html);
        self::assertStringContainsString('Все топики', $html);
        self::assertStringContainsString('mqtt-refresh', $html);
        self::assertStringContainsString('href="/module/mqtt"', $html);
        self::assertStringContainsString('aria-expanded="false"', $html);
        self::assertStringNotContainsString('md-mqtt-status__grid', $html);

        // Событие автообновления приходит в компонент как действие-слушатель.
        Yoyo::request()->reset();
        $_REQUEST = ['component' => 'mqtt-topics/mqtt-refresh', 'yoyo-id' => 'mqtt-topics'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        Yoyo::request()->mock($_REQUEST, $_SERVER);

        $refresh = $app->handle(Request::create('/yoyo', 'POST', [], [], [], [
            'HTTP_X_CSRF_TOKEN' => $app->session()->csrfToken(),
        ]));
        self::assertSame(200, $refresh->getStatusCode());
        self::assertStringContainsString('Все топики', (string)$refresh->getContent());

        // Свёрнутая карточка раскрывается тем же Yoyo и сохраняет состояние
        // при следующем фоновом обновлении.
        Yoyo::request()->reset();
        $_REQUEST = ['component' => 'mqtt-status/toggle', 'yoyo-id' => 'mqtt-status', 'open' => '0'];
        Yoyo::request()->mock($_REQUEST, $_SERVER);
        $expand = $app->handle(Request::create('/yoyo', 'POST', [], [], [], [
            'HTTP_X_CSRF_TOKEN' => $app->session()->csrfToken(),
        ]));
        self::assertSame(200, $expand->getStatusCode());
        self::assertStringContainsString('md-mqtt-status__grid', (string)$expand->getContent());
        self::assertStringContainsString('aria-expanded="true"', (string)$expand->getContent());
        // Состояние уходит в hx-vals обёртки, поэтому следующий фоновый
        // refresh не свернёт карточку обратно.
        self::assertStringContainsString('"open":"1"', (string)$expand->getContent());
    }

    public function testSettingsAreStoredInModuleTable(): void
    {
        $app = $this->boot();
        $response = $app->handle(Request::create('/module/mqtt/settings', 'POST', [
            '_token' => $app->session()->csrfToken(),
            'client_id' => 'Test MQTT',
            'host' => 'broker.local',
            'port' => '1884',
            'query' => 'home/#, /var/now/#',
            'keepalive' => '15',
            'tls' => '1',
            'tls_verify' => '1',
        ]));

        self::assertSame(302, $response->getStatusCode());
        $row = $app->database()->connection()->table('module_mqtt_settings')->where('name', 'host')->first();
        self::assertNotNull($row);
        self::assertSame('broker.local', (string)$row->value);

        $settings = $app->container()->make(Settings::class);
        $settings->refresh(0);
        self::assertSame('broker.local', $settings->string('host'));
        self::assertSame(['home/#', '/var/now/#'], $settings->queries());
        self::assertSame(15, $settings->int('keepalive'));
        self::assertTrue($settings->bool('tls'));

        $page = $app->handle(Request::create('/module/mqtt/settings', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('Проверить соединение', (string)$page->getContent());
        self::assertStringContainsString('Keepalive', (string)$page->getContent());
        self::assertStringContainsString('data-md-mqtt-live', (string)$page->getContent());
        self::assertStringContainsString('mqtt-refresh', (string)$page->getContent());

        $create = $app->handle(Request::create('/module/mqtt/topics/create', 'GET'));
        self::assertSame(200, $create->getStatusCode());
        self::assertStringContainsString('Топик и команда', (string)$create->getContent());
        self::assertStringContainsString('Объект', (string)$create->getContent());
    }

    public function testWorkerRunsServeTaskOnceWithoutBroker(): void
    {
        $app = $this->boot();
        $started = microtime(true);
        $app->container()->make(Worker::class)->run('mqtt', true);

        self::assertLessThan(15.0, microtime(true) - $started);
        $status = $app->container()->make(RuntimeState::class)->status();
        self::assertFalse((bool)$status['connected']);
        self::assertTrue((bool)$status['worker_alive']);
        self::assertNotSame('', (string)$status['last_error']);
    }

    public function testBrokerHandshakeCannotHangForever(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        self::assertIsResource($server, (string)$errorMessage);
        $name = (string)stream_socket_get_name($server, false);
        $port = (int)substr($name, (int)strrpos($name, ':') + 1);

        $client = new SocketClient('127.0.0.1', $port, 'silent-broker-test', \PhpMqtt\Client\MqttClient::MQTT_3_1_1, 1.0);
        $started = microtime(true);
        try {
            // Сервер принимает TCP, но не отвечает CONNACK: раньше это был
            // вечный цикл fread на 100% CPU.
            $client->connect(new \PhpMqtt\Client\ConnectionSettings());
            self::fail('Молчащий брокер не должен считаться подключённым.');
        } catch (\Throwable $error) {
            self::assertLessThan(3.0, microtime(true) - $started);
            self::assertNotSame('', $error->getMessage());
        } finally {
            fclose($server);
        }
    }

    public function testInboundMessageWritesPropertyAndHistory(): void
    {
        $app = $this->boot();
        $service = $app->container()->make(TopicService::class);
        $topic = $service->save([
            'path' => 'sensors/kitchen/temperature',
            'linked_object' => 'MQTT Кухня',
            'linked_property' => 'temperature',
            'logging' => true,
        ]);

        $subscriptions = $app->container()->make(PropertySubscriptionRepository::class)->forModule('mqtt');
        self::assertCount(1, $subscriptions);
        self::assertSame('MQTT Кухня', $subscriptions[0]['object_title']);

        $app->container()->make(InboundHandler::class)->process('sensors/kitchen/temperature', '24.5');

        $values = $app->container()->make(PropertyValueService::class);
        self::assertSame('24.5', $values->get('MQTT Кухня', 'temperature'));

        $repository = $app->container()->make(TopicRepository::class);
        $stored = $repository->find((int)$topic['id']);
        self::assertNotNull($stored);
        self::assertSame('24.5', $stored['value']);
        self::assertNotSame('', $stored['updated']);
        self::assertCount(1, $repository->history((int)$topic['id']));
    }

    public function testPropertyChangePublishesCommand(): void
    {
        $app = $this->boot();
        $fake = new class () implements PublisherInterface {
            /** @var list<array{topic:string,message:string,qos:int,retain:bool}> */
            public array $sent = [];

            public function publish(string $topic, string $message, int $qos = 0, bool $retain = false): void
            {
                $this->sent[] = compact('topic', 'message', 'qos', 'retain');
            }
        };
        // Подписчик берёт интерфейс из контейнера; модульный singleton
        // Publisher заменяем на записывающую реализацию до первого вызова.
        $app->container()->instance(Publisher::class, $fake);

        $app->container()->make(TopicService::class)->save([
            'path' => 'home/living/state',
            'title' => 'Свет',
            'linked_object' => 'MQTT Кухня',
            'linked_property' => 'temperature',
            'qos' => 1,
            'retain' => true,
        ]);

        $app->container()->make(PropertyValueService::class)->setValue(100, 100, '26', 'panel');
        $this->drainEvents($app);

        self::assertCount(1, $fake->sent);
        self::assertSame('home/living/state', $fake->sent[0]['topic']);
        self::assertSame('26', $fake->sent[0]['message']);
        self::assertSame(1, $fake->sent[0]['qos']);
        self::assertTrue($fake->sent[0]['retain']);

        // Эхо от MQTT не публикуется обратно.
        $app->container()->make(PropertyValueService::class)->setValue(100, 100, '27', 'mqtt');
        $this->drainEvents($app);
        self::assertCount(1, $fake->sent);
        self::assertCount(1, $app->container()->make(PropertySubscriptionRepository::class)->forModule('mqtt'));
        self::assertInstanceOf(PropertyPublisher::class, $app->modules()->service('mqtt', PropertyPublisher::class));
    }

    public function testTopicCardAndYoyoUpdate(): void
    {
        $app = $this->boot();
        $topic = $app->container()->make(TopicService::class)->save([
            'path' => 'home/kitchen/humidity',
            'linked_object' => 'MQTT Кухня',
            'linked_property' => 'temperature',
        ]);

        $page = $app->handle(Request::create('/module/mqtt/topics/' . (int)$topic['id'] . '/edit', 'GET'));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('home/kitchen/humidity', (string)$page->getContent());
        self::assertStringContainsString('Привязка', (string)$page->getContent());

        Yoyo::request()->reset();
        $_REQUEST = [
            'component' => 'mqtt-topics/render',
            'yoyo-id' => 'mqtt-topics',
            'search' => 'humidity',
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        Yoyo::request()->mock($_REQUEST, $_SERVER);

        $update = $app->handle(Request::create('/yoyo', 'POST', [], [], [], [
            'HTTP_X_CSRF_TOKEN' => $app->session()->csrfToken(),
        ]));
        self::assertSame(200, $update->getStatusCode());
        self::assertStringContainsString('home/kitchen/humidity', (string)$update->getContent());
    }

    public function testBindingIndexFindsPrefixesAndProperties(): void
    {
        $app = $this->boot();
        $app->container()->make(TopicService::class)->save([
            'path' => 'home/kitchen/json',
            'linked_object' => 'MQTT Кухня',
            'linked_property' => 'temperature',
        ]);

        $index = $app->container()->make(BindingIndex::class);
        $index->reload();
        self::assertTrue($index->isLinked('home/kitchen/json'));
        self::assertTrue($index->hasLinkedPrefix('home/kitchen'));
        self::assertTrue($index->hasLinkedPrefix('home'));
        self::assertCount(1, $index->bindingsFor(100, 100));
    }

    public function testTopicFormCreatesUpdatesAndDeletesBinding(): void
    {
        $app = $this->boot();
        $fake = new class () implements PublisherInterface {
            /** @var list<array{topic:string,message:string,qos:int,retain:bool}> */
            public array $sent = [];

            public function publish(string $topic, string $message, int $qos = 0, bool $retain = false): void
            {
                $this->sent[] = compact('topic', 'message', 'qos', 'retain');
            }
        };
        $app->container()->instance(Publisher::class, $fake);
        $token = $app->session()->csrfToken();
        $create = $app->handle(Request::create('/module/mqtt/topics', 'POST', [
            '_token' => $token,
            'path' => 'home/hall/motion',
            'title' => 'Движение',
            'linked_object' => 'MQTT Кухня',
            'linked_property' => 'temperature',
            'qos' => '1',
            'logging' => '1',
        ]));
        self::assertSame(302, $create->getStatusCode());
        $location = (string)$create->headers->get('Location');
        self::assertStringContainsString('/module/mqtt/topics/', $location);

        $repository = $app->container()->make(TopicRepository::class);
        $topic = $repository->findByPath('home/hall/motion');
        self::assertNotNull($topic);
        self::assertSame('Движение', $topic['title']);
        self::assertSame(1, $topic['qos']);
        self::assertTrue($topic['logging']);
        self::assertCount(1, $app->container()->make(PropertySubscriptionRepository::class)->forModule('mqtt'));

        $publish = $app->handle(Request::create('/module/mqtt/topics/' . (int)$topic['id'] . '/publish', 'POST', [
            '_token' => $token,
            'value' => '42',
        ]));
        self::assertSame(302, $publish->getStatusCode());
        self::assertCount(1, $fake->sent);
        self::assertSame('home/hall/motion', $fake->sent[0]['topic']);
        self::assertSame('42', $fake->sent[0]['message']);
        self::assertSame('42', $repository->find((int)$topic['id'])['value'] ?? '');

        $update = $app->handle(Request::create('/module/mqtt/topics/' . (int)$topic['id'], 'POST', [
            '_token' => $token,
            'path' => 'home/hall/motion',
            'title' => 'Движение 2',
            'linked_object' => '',
            'linked_property' => '',
            'linked_method' => '',
        ]));
        self::assertSame(302, $update->getStatusCode());
        $updated = $repository->find((int)$topic['id']);
        self::assertSame('Движение 2', $updated['title']);
        self::assertSame('', $updated['linked_object']);
        self::assertCount(0, $app->container()->make(PropertySubscriptionRepository::class)->forModule('mqtt'));

        $delete = $app->handle(Request::create('/module/mqtt/topics/' . (int)$topic['id'] . '/delete', 'POST', [
            '_token' => $token,
        ]));
        self::assertSame(302, $delete->getStatusCode());
        self::assertNull($repository->findByPath('home/hall/motion'));
    }

    private function boot(bool $persistent = false): Application
    {
        $session = new ArraySession();
        if ($persistent && $this->database === null) {
            $file = tempnam(sys_get_temp_dir(), 'mydomo_mqtt_');
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
            'environment_file' => sys_get_temp_dir() . '/mydomo-mqtt-env-' . bin2hex(random_bytes(6)),
            'view_cache' => sys_get_temp_dir() . '/mydomo-mqtt-views-' . bin2hex(random_bytes(6)),
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
            $connection->table('classes')->insert([
                'id' => 100,
                'title' => 'MQTT Датчик',
                'parent_id' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $connection->table('properties')->insert([
                'id' => 100,
                'class_id' => 100,
                'object_id' => 0,
                'title' => 'temperature',
                'data_type' => 'float',
                'keep_history' => 1,
                'on_change' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $connection->table('objects')->insert([
                'id' => 100,
                'title' => 'MQTT Кухня',
                'class_id' => 100,
                'location_id' => 0,
                'description' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $app;
    }
}
