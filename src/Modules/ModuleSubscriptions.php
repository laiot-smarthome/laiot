<?php

declare(strict_types=1);

namespace MajorDoMo\Modules;

use InvalidArgumentException;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertySubscriptionRepository;
use RuntimeException;

/**
 * Подписки модуля на изменения свойств — от имени этого же модуля.
 *
 * Модуль не передаёт свой id и не может подписаться за соседа: id задаёт
 * контекст, в котором работает код. Пара «объект + свойство» проверяется по
 * действующим свойствам объекта, чтобы в таблице не появлялись подписки на
 * свойство чужого класса.
 *
 * Выбор подписок делает пользователь в интерфейсе модуля или на общей
 * странице модуля в админке; манифест объявляет только обработчик
 * (`property_subscriber`).
 *
 * 2026-10-27 — фасад добавлен к SDK вместе с общим экраном подписок.
 */
final readonly class ModuleSubscriptions
{
    public function __construct(
        private string $moduleId,
        private bool $canSubscribe,
        private PropertySubscriptionRepository $subscriptions,
        private PropertyRepository $properties,
        private ObjectRepository $objects
    ) {
    }

    /**
     * Подписывает модуль на действующее свойство объекта.
     *
     * Повторная подписка ничего не меняет: выбор пользователя идемпотентен.
     */
    public function attach(int $objectId, int $propertyId): void
    {
        $this->assertCanSubscribe();

        $object = $this->objects->find($objectId);
        if ($object === null) {
            throw new InvalidArgumentException('Объект не найден: ' . $objectId);
        }
        $property = $this->properties->find($propertyId);
        if ($property === null) {
            throw new InvalidArgumentException('Свойство не найдено: ' . $propertyId);
        }
        if (!$this->properties->isApplicableToObject($property, $object)) {
            throw new InvalidArgumentException(
                'Свойство «' . $property['title'] . '» не действует у объекта «' . $object['title'] . '».'
            );
        }
        if (!$this->subscriptions->attach($objectId, $propertyId, $this->moduleId)) {
            throw new RuntimeException('Не удалось сохранить подписку: проверьте таблицу property_subscriptions.');
        }
    }

    public function detach(int $objectId, int $propertyId): bool
    {
        $this->assertCanSubscribe();

        return $this->subscriptions->detach($objectId, $propertyId, $this->moduleId);
    }

    /**
     * Подписки этого модуля с названиями объектов и свойств.
     *
     * @return list<array{object_id:int,object_title:string,property_id:int,property_title:string}>
     */
    public function attached(): array
    {
        return $this->subscriptions->forModule($this->moduleId);
    }

    public function isAttached(int $objectId, int $propertyId): bool
    {
        return in_array($this->moduleId, $this->subscriptions->modulesFor($objectId, $propertyId), true);
    }

    private function assertCanSubscribe(): void
    {
        if (!$this->canSubscribe) {
            throw new RuntimeException(
                'Модуль ' . $this->moduleId . ' не объявляет property_subscriber и не может подписываться на изменения свойств.'
            );
        }
    }
}
