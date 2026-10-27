<?php

declare(strict_types=1);

namespace MajorDoMo\Properties;

use InvalidArgumentException;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Support\Clock;
use Throwable;

/**
 * Подписки модулей на изменения свойств.
 *
 * Подписку выбирает пользователь в интерфейсе модуля: модуль показывает,
 * какие свойства он умеет обслуживать, а ядро хранит выбор установки
 * (объект + свойство + модуль). Манифест модуля подписки не объявляет:
 * в разных системах один и тот же модуль обслуживает разные объекты.
 *
 * Таблица отдельная, а не колонка значения: нет ограничения varchar(255),
 * нет гонки «прочитать-переписать» при параллельной правке подписок.
 *
 * 2026-10-27 — репозиторий создан вместо `LINKED_MODULES` alpha.
 */
final class PropertySubscriptionRepository
{
    /**
     * Результат проверки таблицы запоминается: `modulesFor()` вызывается на
     * каждой записи свойства, а лишний запрос к схеме — лишняя нагрузка.
     */
    private ?bool $tableExists = null;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly Clock $clock
    ) {
    }

    public function hasTable(): bool
    {
        if ($this->tableExists !== null) {
            return $this->tableExists;
        }

        try {
            $this->tableExists = $this->database->connection()->getSchemaBuilder()->hasTable('property_subscriptions');
        } catch (Throwable) {
            $this->tableExists = false;
        }

        return $this->tableExists;
    }

    /**
     * Подписанные на свойство модули в порядке добавления.
     *
     * @return list<string>
     */
    public function modulesFor(int $objectId, int $propertyId): array
    {
        if (!$this->hasTable() || $objectId <= 0 || $propertyId <= 0) {
            return [];
        }

        return $this->database->connection()->table('property_subscriptions')
            ->where('object_id', $objectId)
            ->where('property_id', $propertyId)
            ->orderBy('id')
            ->pluck('module')
            ->map(static fn (mixed $module): string => (string)$module)
            ->all();
    }

    /**
     * Все подписки объекта: пригодится карточке объекта и странице модуля.
     *
     * @return list<array{id:int,property_id:int,property_title:string,module:string}>
     */
    public function forObject(int $objectId): array
    {
        if (!$this->hasTable() || $objectId <= 0) {
            return [];
        }

        return $this->database->connection()->table('property_subscriptions')
            ->leftJoin('properties', 'properties.id', '=', 'property_subscriptions.property_id')
            ->where('property_subscriptions.object_id', $objectId)
            ->orderBy('property_subscriptions.id')
            ->get([
                'property_subscriptions.id',
                'property_subscriptions.property_id',
                'property_subscriptions.module',
                'properties.title as property_title',
            ])
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'property_id' => (int)$row->property_id,
                'property_title' => (string)($row->property_title ?? ''),
                'module' => (string)$row->module,
            ])
            ->all();
    }

    /**
     * Идемпотентно добавляет подписку. Повторный вызов не создаёт дубль.
     */
    public function attach(int $objectId, int $propertyId, string $module): bool
    {
        if (!$this->hasTable() || $objectId <= 0 || $propertyId <= 0) {
            return false;
        }

        $module = $this->module($module);
        $now = $this->clock->nowString();

        try {
            $this->database->connection()->table('property_subscriptions')->insertOrIgnore([
                'object_id' => $objectId,
                'property_id' => $propertyId,
                'module' => $module,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    public function detach(int $objectId, int $propertyId, string $module): bool
    {
        if (!$this->hasTable()) {
            return false;
        }

        return $this->database->connection()->table('property_subscriptions')
            ->where('object_id', $objectId)
            ->where('property_id', $propertyId)
            ->where('module', (string)$module)
            ->delete() > 0;
    }

    /** Удаление свойства снимает и его подписки. */
    public function deleteForProperty(int $propertyId): int
    {
        if (!$this->hasTable() || $propertyId <= 0) {
            return 0;
        }

        return (int)$this->database->connection()->table('property_subscriptions')
            ->where('property_id', $propertyId)
            ->delete();
    }

    /** Удаление объекта снимает подписки на все его свойства. */
    public function deleteForObject(int $objectId): int
    {
        if (!$this->hasTable() || $objectId <= 0) {
            return 0;
        }

        return (int)$this->database->connection()->table('property_subscriptions')
            ->where('object_id', $objectId)
            ->delete();
    }

    private function module(string $module): string
    {
        $module = trim($module);
        if ($module === '' || !preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $module)) {
            throw new InvalidArgumentException('Некорректный id модуля-подписчика: ' . $module);
        }

        return $module;
    }
}
