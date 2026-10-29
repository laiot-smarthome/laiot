<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use LaIOT\Zigbee\Http\BridgesController;
use LaIOT\Zigbee\Http\DevicesController;
use LaIOT\Zigbee\Http\DeviceController;
use LaIOT\Zigbee\Http\JournalController;
use LaIOT\Zigbee\Http\MonitoringController;
use LaIOT\Zigbee\Http\PropertyController;

/**
 * Маршруты Zigbee-модуля.
 *
 * Изменяющие маршруты сами назначают csrf; доступ к интеграции требует права
 * управления настройками, как у MQTT. Вход в сессию добавляет оболочка модуля.
 *
 * 2026-10-28 — маршруты core-модуля Zigbee.
 */
return static function (Router $router): void {
    $router->middleware('can:settings.manage')->group(static function (Router $router): void {
        $router->get('/', DevicesController::class)->name('index');
        $router->post('/devices/configure', [DevicesController::class, 'configure'])->middleware('csrf')->name('devices.configure');
        $router->post('/devices/configure-all', [DevicesController::class, 'configureAll'])->middleware('csrf')->name('devices.configure_all');

        $router->get('/devices/{id}', [DeviceController::class, 'show'])->whereNumber('id')->name('devices.show');
        $router->post('/devices/{id}', [DeviceController::class, 'update'])->whereNumber('id')->middleware('csrf')->name('devices.update');
        $router->post('/devices/{id}/delete', [DeviceController::class, 'delete'])->whereNumber('id')->middleware('csrf')->name('devices.delete');
        $router->post('/devices/{id}/health', [DeviceController::class, 'saveHealth'])->whereNumber('id')->middleware('csrf')->name('devices.health');
        $router->post('/devices/{id}/health/inherit', [DeviceController::class, 'inheritHealth'])->whereNumber('id')->middleware('csrf')->name('devices.health.inherit');

        $router->get('/properties/{id}', [PropertyController::class, 'show'])->whereNumber('id')->name('properties.show');
        $router->post('/properties/{id}', [PropertyController::class, 'update'])->whereNumber('id')->middleware('csrf')->name('properties.update');
        $router->post('/properties/{id}/write', [PropertyController::class, 'write'])->whereNumber('id')->middleware('csrf')->name('properties.write');
        $router->post('/properties/{id}/test', [PropertyController::class, 'test'])->whereNumber('id')->middleware('csrf')->name('properties.test');

        $router->get('/bridges', [BridgesController::class, 'index'])->name('bridges');
        $router->post('/bridges', [BridgesController::class, 'store'])->middleware('csrf')->name('bridges.store');
        $router->post('/bridges/{id}', [BridgesController::class, 'update'])->whereNumber('id')->middleware('csrf')->name('bridges.update');
        $router->post('/bridges/{id}/delete', [BridgesController::class, 'delete'])->whereNumber('id')->middleware('csrf')->name('bridges.delete');
        $router->post('/bridges/{id}/command', [BridgesController::class, 'command'])->whereNumber('id')->middleware('csrf')->name('bridges.command');

        $router->get('/monitoring', [MonitoringController::class, 'show'])->name('monitoring');
        $router->post('/monitoring', [MonitoringController::class, 'save'])->middleware('csrf')->name('monitoring.save');

        $router->get('/journal', [JournalController::class, 'index'])->name('journal');
        $router->post('/journal/clear', [JournalController::class, 'clear'])->middleware('csrf')->name('journal.clear');
    });
};
