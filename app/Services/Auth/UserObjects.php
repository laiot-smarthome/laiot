<?php

declare(strict_types=1);

namespace LaIOT\Services\Auth;

use InvalidArgumentException;
use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Objects\ObjectRepository;
use LaIOT\Services\Properties\PropertyValueService;
use LaIOT\Services\Rename\NameReferenceUpdater;

/**
 * Жизненный цикл объекта учётной записи в классе Users.
 *
 * 2026-10-27 — создание, смена логина и удаление согласованы с пользователем
 * в общей транзакции; файловые ссылки обновляются после её фиксации.
 */
final readonly class UserObjects
{
    public function __construct(
        private DatabaseManager $database,
        private ObjectRepository $objects,
        private PropertyValueService $values,
        private NameReferenceUpdater $references
    ) {
    }

    public function create(string $username, string $name): int
    {
        $classId = $this->database->connection()->table('classes')
            ->where('title', 'Users')->whereNull('deleted_at')->value('id');
        if ($classId === null) {
            throw new InvalidArgumentException('Класс Users не найден. Примените миграции ядра.');
        }
        $objectId = $this->objects->create($username, (int)$classId, 0, $name);
        foreach (['isHome', 'isWifiConnected'] as $property) {
            if (!$this->values->set($username, $property, false, 'user.create')) {
                throw new InvalidArgumentException('В классе Users отсутствует свойство ' . $property . '.');
            }
        }

        return $objectId;
    }

    public function rename(int $objectId, string $username): void
    {
        $object = $this->objects->find($objectId);
        if ($object === null) {
            throw new InvalidArgumentException('Объект пользователя не найден.');
        }
        if ($object['title'] === $username) {
            return;
        }
        $connection = $this->database->connection();
        if ($connection->table('objects')->whereRaw('LOWER(title) = ?', [strtolower($username)])
            ->where('id', '<>', $objectId)->whereNull('deleted_at')->exists()) {
            throw new InvalidArgumentException('Имя нового логина уже занято объектом.');
        }
        $connection->table('objects')->where('id', $objectId)->update([
            'title' => $username, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->references->objectRenamed($objectId, $object['title'], $username);
    }

    public function remove(int $objectId): void
    {
        $this->objects->delete($objectId);
        $connection = $this->database->connection();
        $connection->table('property_values')->where('object_id', $objectId)->delete();
        $connection->table('property_history')->where('object_id', $objectId)->delete();
        foreach (['properties', 'methods'] as $table) {
            $connection->table($table)->where('object_id', $objectId)->update([
                'deleted_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function finishRename(): void
    {
        $this->references->flushModuleFiles();
    }

    public function cancelRename(): void
    {
        $this->references->discardPendingFiles();
    }
}
