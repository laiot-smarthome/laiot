<?php

declare(strict_types=1);

namespace MajorDoMo\Modules\Core\Api;

use MajorDoMo\Modules\ModuleDb;
use MajorDoMo\Modules\ModuleQuery;

/**
 * Хранение входящих webhook: только таблица модуля `webhooks`.
 */
final readonly class WebhookRepository
{
    public function __construct(private ModuleDb $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $rows = [];
        foreach ($this->table()->whereNull('deleted_at')->orderByDesc('id')->get() as $row) {
            $record = $this->row($row);
            if ($record !== null) {
                $rows[] = $record;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->row($this->table()->where('id', $id)->whereNull('deleted_at')->first());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySecretHash(string $hash): ?array
    {
        return $this->row($this->table()->where('secret_hash', $hash)->whereNull('deleted_at')->first());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        return $this->table()->insertGetId($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $this->table()->where('id', $id)->update($data);
    }

    public function touch(int $id, int $status): void
    {
        $now = date('Y-m-d H:i:s');
        $this->table()->where('id', $id)->update([
            'last_used_at' => $now,
            'last_status' => $status,
            'updated_at' => $now,
        ]);
    }

    public function delete(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->table()->where('id', $id)->update([
            'deleted_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function table(): ModuleQuery
    {
        return $this->db->table('webhooks');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(mixed $row): ?array
    {
        if (is_object($row)) {
            return get_object_vars($row);
        }
        if (is_array($row)) {
            return $row;
        }

        return null;
    }
}
