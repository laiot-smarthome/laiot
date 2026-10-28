<?php

declare(strict_types=1);

namespace MajorDoMo\Zigbee;

use MajorDoMo\Events\PropertyChange;
use MajorDoMo\Modules\PropertySubscriber as PropertySubscriberContract;

/**
 * Обратная синхронизация: изменение свойства объекта уходит в устройство.
 *
 * Подписка ставится только для привязок с включённой записью. Изменение,
 * записанное самим Zigbee (источник `zigbee:`), пропускается: иначе входящее
 * сообщение отправлялось бы обратно в устройство бесконечным эхом. То же
 * правило применяется к объектам комнат: их хуки пишут только свои флаги.
 *
 * 2026-10-28 — подписчик перенесён с alpha `PROPERTY_CHANGED` на SDK.
 */
final readonly class PropertySubscriber implements PropertySubscriberContract
{
    public function __construct(private Service $service)
    {
    }

    public function onPropertyChanged(PropertyChange $change): void
    {
        if (str_starts_with($change->source, 'zigbee')) {
            return;
        }
        $this->service->propertyChanged($change->objectTitle, $change->propertyTitle, $change->newValue);
    }
}
