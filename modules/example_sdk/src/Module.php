<?php

declare(strict_types=1);

namespace Example\Sdk;

use MajorDoMo\Modules\Module as BaseModule;
use MajorDoMo\Modules\ModuleContext;

/**
 * Entrypoint примера SDK: демонстрирует boot/install/uninstall-хуки.
 */
final class Module extends BaseModule
{
    public function boot(ModuleContext $context): void
    {
        // Здесь модуль может зарегистрировать сервисы в контейнере.
    }
}
