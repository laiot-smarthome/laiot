<?php

declare(strict_types=1);

namespace Example\Sdk;

use LaIOT\Services\Events\PropertyChange;
use LaIOT\Services\Modules\PropertySubscriber;

/**
 * Пример обработчика изменений свойства.
 *
 * Подписку на конкретные объекты и свойства выбирает пользователь на странице
 * модуля; манифест задаёт только этот класс. Реальный модуль здесь отправлял
 * бы значение во внешнюю систему, а пример складывает имена в память.
 */
final class ExampleSubscriber implements PropertySubscriber
{
    /** @var list<string> */
    public array $seen = [];

    public function onPropertyChanged(PropertyChange $change): void
    {
        $this->seen[] = $change->token();
    }
}
