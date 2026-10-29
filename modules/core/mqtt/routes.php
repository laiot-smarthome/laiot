<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use LaIOT\Mqtt\Http\SettingsController;
use LaIOT\Mqtt\Http\TopicController;
use LaIOT\Mqtt\Http\TopicsController;

/**
 * Маршруты MQTT-модуля.
 *
 * Изменяющие маршруты сами назначают csrf: SDK не включает его по
 * умолчанию. Настройка интеграции требует права управления настройками;
 * вход в сессию уже добавлен оболочкой модуля.
 *
 * 2026-10-27 — маршруты core-модуля MQTT.
 */
return static function (Router $router): void {
    $router->middleware('can:settings.manage')->group(static function (Router $router): void {
        $router->get('/', TopicsController::class)->name('index');

        $router->get('/topics/create', [TopicController::class, 'create'])->name('topics.create');
        $router->post('/topics', [TopicController::class, 'store'])->middleware('csrf')->name('topics.store');
        $router->post('/topics/clear-trash', [TopicsController::class, 'clearTrash'])->middleware('csrf')->name('topics.clear');
        $router->post('/topics/prune-history', [TopicsController::class, 'pruneHistory'])->middleware('csrf')->name('topics.prune');
        $router->get('/topics/{id}/edit', [TopicController::class, 'edit'])->whereNumber('id')->name('topics.edit');
        $router->post('/topics/{id}', [TopicController::class, 'update'])->whereNumber('id')->middleware('csrf')->name('topics.update');
        $router->post('/topics/{id}/delete', [TopicController::class, 'delete'])->whereNumber('id')->middleware('csrf')->name('topics.delete');
        $router->post('/topics/{id}/publish', [TopicController::class, 'publish'])->whereNumber('id')->middleware('csrf')->name('topics.publish');

        $router->get('/settings', [SettingsController::class, 'show'])->name('settings');
        $router->post('/settings', [SettingsController::class, 'save'])->middleware('csrf')->name('settings.save');
        $router->post('/settings/test', [SettingsController::class, 'test'])->middleware('csrf')->name('settings.test');
    });
};
