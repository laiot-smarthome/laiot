<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use MajorDoMo\Modules\Core\Settings\SettingsController;

return static function (Router $router): void {
    $router->get('/', [SettingsController::class, 'index'])->name('index');
    $router->get('/{id}/edit', [SettingsController::class, 'edit'])
        ->whereNumber('id')
        ->name('edit');
    $router->post('/{id}', [SettingsController::class, 'save'])
        ->whereNumber('id')
        ->middleware('csrf')
        ->name('save');
};
