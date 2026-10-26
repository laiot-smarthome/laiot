<?php

declare(strict_types=1);

namespace MajorDoMo\Foundation\Providers;

use MajorDoMo\Api\Webhooks\WebhookRepository;
use MajorDoMo\Api\Webhooks\WebhookService;
use MajorDoMo\Audit\ActionLogger;
use MajorDoMo\Audit\ActionLogRepository;
use MajorDoMo\Auth\AdminAuthenticator;
use MajorDoMo\Auth\ApiTokenRepository;
use MajorDoMo\Auth\Authorization;
use MajorDoMo\Auth\RoleRepository;
use MajorDoMo\Auth\UserRepository;
use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Database\SettingsRepository;
use MajorDoMo\Docs\KnowledgeBase;
use MajorDoMo\Events\PropertyEventRepository;
use MajorDoMo\Foundation\ServiceProvider;
use MajorDoMo\History\HistoryRepository;
use MajorDoMo\History\PropertyHistoryService;
use MajorDoMo\LinkedObject\LinkedObjectService;
use MajorDoMo\Locations\LocationRepository;
use MajorDoMo\Logging\ErrorRepository;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Methods\MethodRunner;
use MajorDoMo\Notifications\NotificationService;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Panel\DashboardStats;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Properties\ValueRepository;
use MajorDoMo\Runtime\CodeRunner;
use MajorDoMo\Runtime\TimeoutRepository;
use MajorDoMo\Scripts\ScriptRepository;
use MajorDoMo\Scripts\ScriptRunner;
use MajorDoMo\Search\PanelSearch;

/**
 * Репозитории и доменные операции ядра; зависимости собирает контейнер.
 *
 * Изменения:
 * 2026-10-26 — общие экземпляры сервисов сохранены при переходе на провайдеры.
 */
final class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ([
            SettingsRepository::class,
            RoleRepository::class,
            ApiTokenRepository::class,
            ActionLogRepository::class,
            ActionLogger::class,
            ErrorRepository::class,
            LocationRepository::class,
            ScriptRepository::class,
            ClassRepository::class,
            PropertyRepository::class,
            MethodRepository::class,
            ObjectRepository::class,
            LinkedObjectService::class,
            MethodRunner::class,
            TimeoutRepository::class,
            CodeRunner::class,
            ScriptRunner::class,
            WebhookRepository::class,
            WebhookService::class,
            HistoryRepository::class,
            PropertyHistoryService::class,
            ValueRepository::class,
            PropertyEventRepository::class,
            // Цепочка OnChange хранит глубину/родителя между вложенными sg().
            PropertyValueService::class,
            Authorization::class,
            UserRepository::class,
            AdminAuthenticator::class,
            PanelSearch::class,
            DashboardStats::class,
            NotificationService::class,
        ] as $service) {
            $this->container->singleton($service);
        }
        $this->container->singleton(KnowledgeBase::class, fn (): KnowledgeBase => new KnowledgeBase($this->app->root() . '/docs'));
    }
}
