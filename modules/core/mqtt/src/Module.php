<?php

declare(strict_types=1);

namespace MajorDoMo\Mqtt;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Container\Container;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Events\PropertyEventQueue;
use MajorDoMo\Locations\LocationRepository;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Methods\MethodRunner;
use MajorDoMo\Modules\Module as BaseModule;
use MajorDoMo\Modules\ModuleContext;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Mqtt\Yoyo\Status as StatusComponent;
use MajorDoMo\Mqtt\Yoyo\Topics as TopicsComponent;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Queue\QueueWorker;
use MajorDoMo\Worker\BackgroundTaskContext;
use MajorDoMo\Worker\ProcessLock;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * MQTT-модуль ядра.
 *
 * Модуль входит в поставку: его нельзя удалить, но можно выключить. Пока он
 * включён, его worker держит одно долгоживущее соединение с брокером:
 * принимает сообщения, применяет привязки и тут же отправляет команды из
 * очереди `module.mqtt` через то же соединение.
 *
 * Цикл приёма живёт в фоновой задаче и ограничен её timeout; worker
 * перезапускает задачу, поэтому утечки памяти и «вечный» процесс не
 * копятся. Между пакетами выполняется разбор очереди команд, поэтому
 * отправка не ждёт следующего входящего сообщения.
 *
 * 2026-10-27 — MQTT перенесён из alpha в core-модуль на SDK и Yoyo.
 */
final class Module extends BaseModule
{
    public function boot(ModuleContext $context): void
    {
        $container = $context->container();
        $moduleDb = $context->db();
        if ($moduleDb === null) {
            throw new RuntimeException('MQTT-модулю нужен storage.tables в module.json.');
        }

        $container->singleton(Schema::class, static fn (): Schema => new Schema($moduleDb));
        $container->singleton(Settings::class, static fn (Container $c): Settings => new Settings(
            $c->make(Schema::class),
            $moduleDb,
            $c->make(\MajorDoMo\Cache\CacheStore::class)
        ));
        $container->singleton(TopicRepository::class, static fn (Container $c): TopicRepository => new TopicRepository(
            $c->make(Schema::class),
            $moduleDb,
            $c->make(\MajorDoMo\Cache\CacheStore::class)
        ));
        $container->singleton(BindingIndex::class, static fn (Container $c): BindingIndex => new BindingIndex(
            $c->make(TopicRepository::class),
            $c->make(\MajorDoMo\Cache\CacheStore::class)
        ));
        $container->singleton(RuntimeState::class, static fn (Container $c): RuntimeState => new RuntimeState(
            $c->make(\MajorDoMo\Cache\CacheStore::class),
            $c->make(Settings::class)
        ));
        $container->singleton(Broker::class, static fn (Container $c): Broker => new Broker($c->make(Settings::class)));
        $container->singleton(Publisher::class, static fn (Container $c): Publisher => new Publisher(
            $c->make(Settings::class),
            $c->make(Broker::class),
            $c->make(RuntimeState::class),
            $c->make(LoggerInterface::class)
        ));
        $container->alias(Publisher::class, PublisherInterface::class);
        $container->singleton(InboundHandler::class, static fn (Container $c): InboundHandler => new InboundHandler(
            $c->make(Settings::class),
            $c->make(BindingIndex::class),
            $c->make(TopicRepository::class),
            $c->make(PropertyValueService::class),
            $c->make(MethodRunner::class),
            $c->make(RuntimeState::class),
            $c->make(LoggerInterface::class)
        ));
        $container->singleton(PropertyPublisher::class, static fn (Container $c): PropertyPublisher => new PropertyPublisher(
            $c->make(BindingIndex::class),
            $c->make(TopicRepository::class),
            $c->make(PublisherInterface::class),
            $c->make(Settings::class),
            $c->make(RuntimeState::class),
            $c->make(LoggerInterface::class)
        ));
        $container->singleton(TopicService::class, static fn (Container $c): TopicService => new TopicService(
            $c->make(TopicRepository::class),
            $c->make(BindingIndex::class),
            $context->subscriptions(),
            $c->make(Schema::class),
            $c->make(ObjectRepository::class),
            $c->make(PropertyRepository::class),
            $c->make(MethodRepository::class),
            $c->make(LocationRepository::class),
            $c->make(PublisherInterface::class),
            $c->make(Broker::class),
            $c->make(LoggerInterface::class)
        ));

        // Yoyo разрешает kebab-case по зарегистрированному классу модуля.
        // Шаблоны лежат в views/yoyo и подключены как namespace `mqtt`.
        Yoyo::registerComponent('mqtt-topics', TopicsComponent::class);
        Yoyo::registerComponent('mqtt-status', StatusComponent::class);
    }

    /**
     * Один длинный проход: приём, привязки и отправка команд.
     */
    public function serve(BackgroundTaskContext $context): void
    {
        $container = $context->module->container();
        $this->freshDatabase($container);

        if (!$this->moduleEnabled($container)) {
            // Выключенный core-модуль не должен подключаться к брокеру.
            // Спим вместо мгновенного выхода: иначе worker перезапускал бы
            // задачу каждую секунду, пока unit не остановят вручную.
            if (!$context->once) {
                sleep(30);
            }

            return;
        }

        /** @var Settings $settings */
        $settings = $container->make(Settings::class);
        $container->make(Schema::class)->ensure();
        /** @var RuntimeState $runtime */
        $runtime = $container->make(RuntimeState::class);
        /** @var Broker $broker */
        $broker = $container->make(Broker::class);
        /** @var InboundHandler $inbound */
        $inbound = $container->make(InboundHandler::class);
        /** @var Publisher $publisher */
        $publisher = $container->make(Publisher::class);
        $publisher->useSharedBroker(true);
        $settings->refresh(0);

        $stop = false;
        $this->installSignals($stop);

        // Порядок команд одного свойства сохраняет один потребитель очереди.
        // Если отдельный core-worker уже держит её, отправка остаётся на нём.
        $moduleQueue = $container->make(PropertyEventQueue::class)->moduleQueueName('mqtt');
        $deliveryLock = new ProcessLock($context->module->root() . '/var/locks');
        $ownsDelivery = false;
        try {
            $ownsDelivery = $deliveryLock->acquire(['queue.' . $moduleQueue]);
        } catch (Throwable) {
            $ownsDelivery = false;
        }
        $queueWorker = $ownsDelivery ? $container->make(QueueWorker::class) : null;

        // Linux: длинный цикл в дочернем процессе worker-а. Windows без pcntl
        // исполняет задачу в текущем процессе, поэтому там цикл короткий —
        // это площадка разработки, а не рабочий runtime.
        $maxRun = PHP_OS_FAMILY === 'Windows' ? 30.0 : 3500.0;
        $deadline = $context->once
            ? microtime(true) + 2.0
            : min($context->deadline() - 10.0, microtime(true) + $maxRun);
        $loopStarted = microtime(true);
        $connected = false;
        $reconnectDelay = 0.5;
        $lastConnectAttempt = 0.0;
        $lastSettingsCheck = microtime(true);
        $lastEnabledCheck = 0.0;

        try {
            while (!$stop && microtime(true) < $deadline) {
                $now = microtime(true);

                if ($now - $lastEnabledCheck >= 5.0) {
                    $lastEnabledCheck = $now;
                    if (!$this->moduleEnabled($container)) {
                        break;
                    }
                }

                if (!$connected) {
                    if ($now - $lastConnectAttempt < $reconnectDelay) {
                        usleep(100000);
                        continue;
                    }
                    $lastConnectAttempt = $now;
                    try {
                        $settings->refresh(1.0);
                        $broker->connect([$inbound, 'handle']);
                        $connected = true;
                        $reconnectDelay = 0.5;
                        $runtime->setConnected(true);
                    } catch (Throwable $error) {
                        $connected = false;
                        $runtime->setConnected(false, $error->getMessage());
                        $runtime->flush(true);
                        $reconnectDelay = min(30.0, max(0.5, $reconnectDelay * 2));
                        continue;
                    }
                }

                try {
                    $broker->pump($loopStarted, 20000);
                } catch (Throwable $error) {
                    $runtime->setConnected(false, $error->getMessage());
                    $runtime->flush(true);
                    $broker->close();
                    $connected = false;
                    $reconnectDelay = 1.0;
                    continue;
                }

                if ($queueWorker !== null) {
                    $handled = 0;
                    while ($handled < 50 && $queueWorker->work($moduleQueue)) {
                        $handled++;
                        $runtime->count('delivered');
                    }
                }

                $inbound->retryDue(20);
                $inbound->flush();
                $runtime->flush();

                if ($now - $lastSettingsCheck >= 5.0) {
                    $lastSettingsCheck = $now;
                    if ($settings->refresh(5.0)) {
                        // Сохранение формы меняет брокера/подписки: чистое
                        // переподключение дешевле, чем точечная синхронизация.
                        $broker->close();
                        $connected = false;
                        $reconnectDelay = 0.5;
                    }
                }
            }
        } finally {
            $publisher->useSharedBroker(false);
            $broker->close();
            $runtime->setConnected(false);
            $runtime->flush(true);
            if ($ownsDelivery) {
                $deliveryLock->release();
            }
        }
    }

    /**
     * Периодическая уборка: срок истории и сброс накопленного состояния.
     */
    public function maintenance(BackgroundTaskContext $context): void
    {
        $container = $context->module->container();
        $this->freshDatabase($container);
        if (!$this->moduleEnabled($container)) {
            return;
        }
        /** @var Settings $settings */
        $settings = $container->make(Settings::class);
        $settings->refresh(0);
        $container->make(Schema::class)->ensure();
        /** @var TopicRepository $topics */
        $topics = $container->make(TopicRepository::class);
        $removed = $topics->pruneHistory($settings->int('history_keep_days'));
        if ($removed > 0) {
            $container->make(LoggerInterface::class)->info('MQTT: история очищена', ['rows' => $removed]);
        }
        $container->make(RuntimeState::class)->flush(true);
    }

    public function disabled(ModuleContext $context): void
    {
        // Строка реестра сообщает циклу о выключении; счётчики и heartbeat
        // сбрасываем, чтобы страница после включения не показывала старый
        // снимок и мнимый живой процесс.
        try {
            $context->container()->make(RuntimeState::class)->resetCounters();
        } catch (Throwable) {
            // Таблиц/кэша может не быть на свежей установке — это не ошибка хука.
        }
    }

    /**
     * Дочерний процесс после fork не должен использовать PDO родителя:
     * общий TCP-сокет сдвигает состояние протокола и ломает следующий запрос
     * worker-а. SQLite в памяти тестов не переоткрываем — данные пропали бы.
     *
     * @param Container $container
     */
    private function freshDatabase(Container $container): void
    {
        try {
            /** @var DatabaseManager $database */
            $database = $container->make(DatabaseManager::class);
            if (!$database->isConfigured()) {
                return;
            }
            $config = $database->config();
            if ((string)($config['driver'] ?? '') === 'sqlite') {
                return;
            }
            $database->connection()->disconnect();
        } catch (Throwable) {
            // Подключение ещё не открывалось — переоткрывать нечего.
        }
    }

    private function moduleEnabled(Container $container): bool
    {
        try {
            /** @var ModuleManager $modules */
            $modules = $container->make(ModuleManager::class);
            $row = $modules->registry()->find('mqtt');

            return $row === null || (int)$row['enabled'] === 1;
        } catch (Throwable) {
            // Таблица реестра не создана: модуль поставки считается включённым.
            return true;
        }
    }

    private function installSignals(bool &$stop): void
    {
        if (!function_exists('pcntl_async_signals') || !function_exists('pcntl_signal')) {
            return;
        }
        pcntl_async_signals(true);
        $handler = static function () use (&$stop): void {
            $stop = true;
        };
        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
    }
}
