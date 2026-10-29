<?php

declare(strict_types=1);

namespace LaIOT\Services\Modules;

use LaIOT\Services\Events\PropertyChange;

/**
 * Обработчик изменений свойств, который предоставляет SDK-модуль.
 *
 * Подписки не объявляются в манифесте: пользователь сам выбирает в интерфейсе
 * модуля, какие объекты и свойства этот модуль обслуживает. Манифест говорит
 * только, какой класс умеет принимать изменения (`property_subscriber`).
 *
 * Ошибка обработчика не откатывает сохранённое значение: она попадает в
 * журнал `property_events` шагом `module.delivery` со статусом `error`.
 *
 * 2026-10-27 — контракт создан для доставки изменений свойства модулям.
 */
interface PropertySubscriber
{
    public function onPropertyChanged(PropertyChange $change): void;
}
