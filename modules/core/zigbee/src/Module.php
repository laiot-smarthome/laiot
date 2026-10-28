<?php

declare(strict_types=1);

namespace MajorDoMo\Zigbee;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Container\Container;
use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Locations\LocationRepository;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Methods\MethodRunner;
use MajorDoMo\Modules\Module as BaseModule;
use MajorDoMo\Modules\ModuleContext;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Mqtt\Integrations;
use MajorDoMo\Mqtt\PublisherInterface;
use MajorDoMo\Mqtt\RuntimeState;
use MajorDoMo\Notifications\NotificationService;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Queue\JobRegistry;
use MajorDoMo\Queue\Queue;
use MajorDoMo\Runtime\CodeRunner;
use MajorDoMo\Worker\BackgroundTaskContext;
use MajorDoMo\Worker\ProcessLock;
use MajorDoMo\Zigbee\Yoyo\Devices as DevicesComponent;
use MajorDoMo\Zigbee\Yoyo\Journal as JournalComponent;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Zigbee-модуль ядра.
 *
 * Модуль входит в поставку и зависит от MQTT: собственного соединения нет.
 * MQTT-цикл отдаёт сообщения владельцу, тот ставит задание `module.zigbee`,
 * а этот worker разбирает пакеты, применяет привязки и отправляет команды.
 *
 * Выключение модуля убирает маршруты, меню и регистрацию интеграции; данные
 * экземпляров, устройств и привязок сохраняются.
 *
 * 2026-10-28 — перенос из alpha в core-модуль на SDK/Yoyo.
 */
final class Module extends BaseModule
{
    public function boot(ModuleContext $context): void
    {
        $container = $context->container();
        $moduleDb = $context->db();
        if ($moduleDb === null) {
            throw new RuntimeException('Zigbee-модулю нужен storage.tables в module.json.');
        }
        $root = $context->root();

        $container->singleton(Schema::class, static fn (): Schema => new Schema($moduleDb));
        $container->singleton(
            Repository::class,
            static fn (Container $c): Repository => new Repository($moduleDb, $c->make(Schema::class))
        );
        $container->singleton(Service::class, static fn (Container $c): Service => new Service(
            $c->make(Repository::class),
            $c->make(PublisherInterface::class),
            $c->make(PropertyValueService::class),
            $c->make(MethodRunner::class),
            $c->make(MethodRepository::class),
            $c->make(ClassRepository::class),
            $c->make(ObjectRepository::class),
            $c->make(LocationRepository::class),
            $c->make(LoggerInterface::class)
        ));
        $container->singleton(MqttIntegration::class, static fn (Container $c): MqttIntegration => new MqttIntegration(
            $c->make(Service::class),
            $c->make(Queue::class)
        ));
        $container->singleton(HealthMonitor::class, static fn (Container $c): HealthMonitor => new HealthMonitor(
            $c->make(Repository::class),
            $c->make(RuntimeState::class),
            $c->make(NotificationService::class),
            $c->make(CodeRunner::class),
            new ProcessLock($root . '/var/locks'),
            $c->make(LocationRepository::class)
        ));
        $container->singleton(MessageHandler::class);

        // Задание ставит MQTT-цикл; обработчик обязан существовать в процессе,
        // который обслуживает `core-worker module.zigbee` или `worker zigbee`.
        $container->make(JobRegistry::class)->register(
            MqttIntegration::JOB,
            static function (array $payload) use ($container): void {
                $container->make(MessageHandler::class)->handle($payload);
            }
        );

        // Сервис интеграции зависит от MethodRunner → NotificationService →
        // ModuleManager, который сейчас ещё собирается. Ленивая регистрация
        // разрывает эту рекурсию: объект создаётся после boot.
        $integrations = $container->make(Integrations::class);
        $integrations->addLazy('zigbee', static fn (): MqttIntegration => $container->make(MqttIntegration::class));
        $container->instance(Integrations::class, $integrations);

        Yoyo::registerComponent('zigbee-devices', DevicesComponent::class);
        Yoyo::registerComponent('zigbee-journal', JournalComponent::class);
    }

    /**
     * Проход мониторинга: доступность, батареи и инциденты.
     */
    public function monitor(BackgroundTaskContext $context): void
    {
        $container = $context->module->container();
        if (!$this->moduleEnabled($container)) {
            return;
        }
        $container->make(HealthMonitor::class)->tick();
    }

    public function disabled(ModuleContext $context): void
    {
        // Регистрация интеграции живёт в процессе MQTT-цикла до его
        // перезапуска: честно сообщаем об этом в журнал один раз.
        try {
            $context->container()->make(LoggerInterface::class)->info(
                'Zigbee: модуль выключен, MQTT перестанет принимать его топики после перезапуска worker-а.'
            );
        } catch (Throwable) {
            // Журнал может быть недоступен на свежей установке.
        }
    }

    private function moduleEnabled(Container $container): bool
    {
        try {
            /** @var ModuleManager $modules */
            $modules = $container->make(ModuleManager::class);
            $row = $modules->registry()->find('zigbee');

            return $row === null || (int)$row['enabled'] === 1;
        } catch (Throwable) {
            // Таблица реестра не создана: модуль поставки считается включённым.
            return true;
        }
    }
}
