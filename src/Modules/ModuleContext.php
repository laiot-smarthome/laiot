<?php

declare(strict_types=1);

namespace MajorDoMo\Modules;

use Illuminate\Container\Container;

/**
 * Контекст, который SDK передаёт в entrypoint модуля.
 *
 * Модуль получает только свой ModuleDb/ModuleBus и контейнер; root и чужие
 * таблицы напрямую недоступны.
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

    public function container(): Container
    {
        return $this->container;
    }

    public function root(): string
    {
        return $this->root;
    }
}
