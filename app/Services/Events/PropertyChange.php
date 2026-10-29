<?php

declare(strict_types=1);

namespace LaIOT\Services\Events;

/**
 * Одно изменение свойства, которое получает подписанный модуль.
 *
 * Объект неизменяемый: доставка не должна править данные, которые увидит
 * следующий подписчик. `changed=false` означает, что значение записали
 * повторно (например, датчик прислал то же число): история не растёт, но
 * подписчик получает вызов так же, как OnChange-метод.
 *
 * 2026-10-27 — объект создан для доставки изменений свойства модулям.
 */
final readonly class PropertyChange
{
    /**
     * @param list<string> $chain Свойства, уже пройденные этой цепочкой записи.
     */
    public function __construct(
        public int $eventId,
        public int $objectId,
        public int $propertyId,
        public string $objectTitle,
        public string $propertyTitle,
        public string $oldValue,
        public string $newValue,
        public string $source,
        public bool $changed,
        public array $chain,
        public int $depth
    ) {
    }

    /** Идентификатор свойства в виде `Объект.Свойство`. */
    public function token(): string
    {
        return $this->objectTitle . '.' . $this->propertyTitle;
    }
}
