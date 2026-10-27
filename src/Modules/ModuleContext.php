<?php

declare(strict_types=1);

namespace MajorDoMo\Modules;

use Illuminate\Container\Container;
use MajorDoMo\Events\SystemEvents;

/**
 * Контекст, который SDK передаёт в entrypoint модуля.
 *
 * Модуль получает только свой ModuleDb/ModuleBus и контейнер; root и чужие
 * таблицы напрямую недоступны. Настройки и подписки на свойства выдаются
 * сервисами, привязанными к id модуля: подписаться за соседний модуль нельзя.
 *
 * 2026-10-27 — подписка модуля на системные события.
 * 2026-10-27 — settings() и subscriptions() вместо доступа к реестру напрямую.
 */
final readonly class ModuleContext
{
    /**
     * @param array<string, mixed> $manifest
     */
    public function __construct(
        public array $manifest,
        private ModuleBus $bus,
        private Container $container,
        private string $root,
        private ModuleSettings $settings,
        private ModuleSubscriptions $subscriptions,
        private ?ModuleDb $db = null
    ) {
    }

    public function id(): string
    {
        return (string)$this->manifest['id'];
    }

    public function version(): string
    {
        return (string)($this->manifest['version'] ?? '0.0.0');
    }

    public function db(): ?ModuleDb
    {
        return $this->db;
    }

    public function bus(): ModuleBus
    {
        return $this->bus;
    }

    public function settings(): ModuleSettings
    {
        return $this->settings;
    }

    public function subscriptions(): ModuleSubscriptions
    {
        return $this->subscriptions;
    }

    public function onSystemEvent(string $event, callable $listener): void
    {
        $this->container->make(SystemEvents::class)->subscribe($this->id(), $event, $listener);
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function root(): string
    {
        return $this->root;
    }
}
