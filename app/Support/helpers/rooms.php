<?php

declare(strict_types=1);

use LaIOT\Services\Locations\RoomActivityService;
use LaIOT\Support\AppContext;

/**
 * Быстрые проверки комнаты для сценариев и методов.
 *
 * Название — название локации («Кухня») или автоматического объекта комнаты
 * («Kuhnya»). Значения читаются из свойств объекта комнаты, которые система
 * обновляет при изменениях свойств с ролью. Загружать что-либо вручную не
 * нужно: в запросе и в worker приложение уже поднято.
 *
 * 2026-10-27 — хелперы созданы вместе с автоматическим учётом активности.
 */
if (!function_exists('roomState')) {
    /**
     * @return array{
     *     location_id:int,title:string,object_id:int,object_title:string,
     *     active:bool,light:bool,heating:bool,condition:bool,
     *     timeout:int,last_activity_at:string
     * }|null
     */
    function roomState(string $room): ?array
    {
        $app = AppContext::get();
        if ($app === null) {
            return null;
        }

        return $app->container()->make(RoomActivityService::class)->stateFor($room);
    }
}

if (!function_exists('roomIsActive')) {
    function roomIsActive(string $room): bool
    {
        return roomState($room)['active'] ?? false;
    }
}

if (!function_exists('roomIsLight')) {
    function roomIsLight(string $room): bool
    {
        return roomState($room)['light'] ?? false;
    }
}

if (!function_exists('roomIsHeating')) {
    function roomIsHeating(string $room): bool
    {
        return roomState($room)['heating'] ?? false;
    }
}

if (!function_exists('roomIsCondition')) {
    function roomIsCondition(string $room): bool
    {
        return roomState($room)['condition'] ?? false;
    }
}
