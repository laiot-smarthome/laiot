<?php

declare(strict_types=1);

namespace Example\Sdk;

use LaIOT\Services\Modules\Module as BaseModule;
use LaIOT\Services\Modules\ModuleContext;
use LaIOT\Services\Worker\BackgroundTaskContext;

/**
 * Entrypoint примера SDK: демонстрирует boot/install/uninstall-хуки и задачу.
 */
final class Module extends BaseModule
{
    public function boot(ModuleContext $context): void
    {
        // Здесь модуль может зарегистрировать сервисы в контейнере.
    }

    public function tick(BackgroundTaskContext $context): void
    {
        $db = $context->module->db();
        if ($db === null) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $db->table('notes')->insert([
            'title' => 'tick',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
