<?php

declare(strict_types=1);

namespace MajorDoMo\Modules;

/**
 * База entrypoint модуля SDK.
 *
 * Все методы опциональны: модуль переопределяет boot/install/uninstall/
 * enabled/disabled по необходимости. Маршруты и представления по-прежнему
 * описываются в module.json.
 */
abstract class Module
{
    public function boot(ModuleContext $context): void
    {
    }

    public function install(ModuleContext $context): void
    {
    }

    public function uninstall(ModuleContext $context): void
    {
    }

    public function enabled(ModuleContext $context): void
    {
    }

    public function disabled(ModuleContext $context): void
    {
    }
}
