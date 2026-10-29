<?php

declare(strict_types=1);

namespace MajorDoMo\Foundation\Providers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use MajorDoMo\Api\Webhooks\WebhookRepository;
use MajorDoMo\Api\Webhooks\WebhookService;
use MajorDoMo\Audit\ActionLogger;
use MajorDoMo\Audit\ActionLogRepository;
use MajorDoMo\Auth\AdminAuthenticator;
use MajorDoMo\Auth\ApiTokenRepository;
use MajorDoMo\Auth\Authorization;
use MajorDoMo\Auth\RoleRepository;
use MajorDoMo\Auth\UserObjects;
use MajorDoMo\Auth\UserRepository;
use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Database\SettingsRepository;
use MajorDoMo\Docs\KnowledgeBase;
use MajorDoMo\Events\ModulePropertyHandler;
use MajorDoMo\Events\PropertyEventContext;
use MajorDoMo\Events\PropertyEventHandler;
use MajorDoMo\Events\PropertyEventQueue;
use MajorDoMo\Events\PropertyEventRepository;
use MajorDoMo\Events\PropertyExecutionRepository;
use MajorDoMo\Events\SystemEvents;
use MajorDoMo\Events\SystemEventTicker;
use MajorDoMo\Foundation\ServiceProvider;
use MajorDoMo\History\HistoryRepository;
use MajorDoMo\History\PropertyHistoryService;
use MajorDoMo\LinkedObject\LinkedObjectService;
use MajorDoMo\Locations\LocationRepository;
use MajorDoMo\Locations\RoomActivityService;
use MajorDoMo\Locations\RoomProvisioner;
use MajorDoMo\Logging\ErrorRepository;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Methods\MethodRunner;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Notifications\NotificationService;
use MajorDoMo\Notifications\SayService;
use MajorDoMo\Notifications\SaySource;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Panel\DashboardStats;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertySubscriptionRepository;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Properties\ValueRepository;
use MajorDoMo\Queue\JobRegistry;
use MajorDoMo\Queue\Queue;
use MajorDoMo\Rename\CodeReferenceRewriter;
use MajorDoMo\Rename\NameReferenceUpdater;
use MajorDoMo\Runtime\CodeRunner;
use MajorDoMo\Runtime\PhpSyntaxValidator;
use MajorDoMo\Runtime\TimeoutRepository;
use MajorDoMo\Runtime\UserCodeDeadline;
use MajorDoMo\Scripts\ScriptEventDispatcher;
use MajorDoMo\Scripts\ScriptEventJob;
use MajorDoMo\Scripts\ScriptExecutor;
use MajorDoMo\Scripts\ScriptRepository;
use MajorDoMo\Scripts\ScriptRunner;
use MajorDoMo\Search\PanelSearch;
use MajorDoMo\Settings\DatabaseSettingsGuard;
use MajorDoMo\Settings\EnvFile;
use MajorDoMo\Settings\SettingsSchema;
use MajorDoMo\Settings\SettingsService;
use MajorDoMo\Support\Clock;

/**
 * Репозитории и доменные операции ядра; зависимости собирает контейнер.
 *
 * Изменения:
 * 2026-10-29 — say получает определение источника и минимальную важность доставки.
 * 2026-10-29 — центр уведомлений получает окно повторов из конфигурации.
 * 2026-10-29 — say использует отдельный сервис журнала и фоновой доставки.
 * 2026-10-27 — жизненный цикл объекта пользователя зарегистрирован как сервис.
 * 2026-10-27 — редактор системного окружения зарегистрирован отдельно от DB-настроек.
 * 2026-10-27 — общее долговечное состояние цепочек свойств.
 * 2026-10-27 — сценарии запускаются по системному событию; код проверяется до сохранения.
 * 2026-10-27 — регистрация системных событий и их доставки модулям.
 * 2026-10-27 — обработчик `property.changed` регистрируется для core-worker events.
 * 2026-10-27 — комнаты получают провижининг класса/объекта и сервис активности.
 * 2026-10-26 — общие экземпляры сервисов сохранены при переходе на провайдеры.
 */
final class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(SettingsService::class, fn (Container $container): SettingsService => new SettingsService(
            new EnvFile((string)$this->app->config('environment_file', $this->app->root() . '/.env')),
            SettingsSchema::defaults($this->app->root()),
            $container->make(DatabaseSettingsGuard::class)
        ));
        foreach ([
            SettingsRepository::class,
            RoleRepository::class,
            ApiTokenRepository::class,
            ActionLogRepository::class,
            ActionLogger::class,
            ErrorRepository::class,
            LocationRepository::class,
            RoomProvisioner::class,
            RoomActivityService::class,
            ScriptRepository::class,
            ClassRepository::class,
            PropertyRepository::class,
            PropertySubscriptionRepository::class,
            MethodRepository::class,
            ObjectRepository::class,
            LinkedObjectService::class,
            MethodRunner::class,
            TimeoutRepository::class,
            CodeRunner::class,
            PhpSyntaxValidator::class,
            CodeReferenceRewriter::class,
            NameReferenceUpdater::class,
            ScriptExecutor::class,
            ScriptRunner::class,
            ScriptEventDispatcher::class,
            ScriptEventJob::class,
            WebhookRepository::class,
            WebhookService::class,
            HistoryRepository::class,
            PropertyHistoryService::class,
            ValueRepository::class,
            PropertyEventRepository::class,
            PropertyExecutionRepository::class,
            // Контекст события: цепочка, родитель журнала и модуль-автор
            // для вложенных записей внутри обработчика.
            PropertyEventContext::class,
            SystemEvents::class,
            SystemEventTicker::class,
            PropertyEventHandler::class,
            PropertyValueService::class,
            Authorization::class,
            UserRepository::class,
            UserObjects::class,
            AdminAuthenticator::class,
            PanelSearch::class,
            DashboardStats::class,
        ] as $service) {
            $this->container->singleton($service);
        }
        $this->container->singleton(KnowledgeBase::class, fn (): KnowledgeBase => new KnowledgeBase($this->app->root() . '/docs'));
        $this->container->singleton(NotificationService::class, fn (Container $container): NotificationService => new NotificationService(
            $container->make(DatabaseManager::class),
            $container->make(ModuleManager::class),
            $container->make(Clock::class),
            (int)$this->app->config('notifications.duplicate_window'),
            $container->make(ValidationFactory::class)
        ));
        $this->container->singleton(SaySource::class, fn (): SaySource => new SaySource($this->app->root()));
        $this->container->singleton(SayService::class, fn (Container $container): SayService => new SayService(
            $container->make(NotificationService::class),
            $container->make(DatabaseManager::class),
            $container->make(SystemEvents::class),
            $container->make(SaySource::class),
            $container->make(ValidationFactory::class),
            (int)$this->app->config('notifications.minimum_importance')
        ));
        $this->container->singleton(UserCodeDeadline::class, function (): UserCodeDeadline {
            $timeout = (int)$this->app->config('queue.handler_timeout', 5);
            if ($timeout >= (int)$this->app->config('queue.reservation_timeout', 300)) {
                throw new \RuntimeException('queue.handler_timeout должен быть меньше reservation_timeout.');
            }
            return new UserCodeDeadline($timeout);
        });
        $this->container->singleton(
            PropertyEventQueue::class,
            fn (Container $container): PropertyEventQueue => new PropertyEventQueue(
                $container->make(Queue::class),
                (array)$this->app->config('queue', [])
            )
        );
    }

    /**
     * Регистрирует обработчики очередей ядра.
     *
     * Сами задания выполняет `core-worker`; в HTTP-запросе обработчики только
     * лежат в реестре и не запускают пользовательский код.
     */
    public function boot(): void
    {
        $jobs = $this->container->make(JobRegistry::class);
        $jobs->register(
            'module.system.event',
            [$this->container->make(SystemEvents::class), 'deliver']
        );
        $jobs->register(
            'property.changed',
            $this->container->make(PropertyEventHandler::class)
        );
        $jobs->register(
            'module.property.changed',
            $this->container->make(ModulePropertyHandler::class)
        );
        // Задание и наблюдатель резолвятся лениво: boot не должен создавать
        // ScriptRunner/CodeRunner раньше первого запуска сценария.
        $jobs->register(ScriptEventDispatcher::JOB, $this->scriptEventHandler());

        // Сценарии не модули: генератор событий передаёт их обработчику ядра,
        // а не подписывает как SDK-модуль.
        $this->container->make(SystemEvents::class)->observe($this->scriptEventObserver());
    }

    /**
     * @return callable(array<string, mixed>): void
     */
    private function scriptEventHandler(): callable
    {
        return function (array $payload): void {
            $this->container->make(ScriptEventJob::class)->handle($payload);
        };
    }

    /**
     * @return callable(string, array<string, mixed>): void
     */
    private function scriptEventObserver(): callable
    {
        return function (string $event, array $data): void {
            $this->container->make(ScriptEventDispatcher::class)->dispatch($event, $data);
        };
    }
}
