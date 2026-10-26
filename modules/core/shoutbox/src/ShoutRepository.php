<?php

declare(strict_types=1);

namespace MajorDoMo\Modules\Core\Shoutbox;

use MajorDoMo\Modules\ModuleDb;

/**
 * Данные Shoutbox: только таблица модуля, только через ModuleDb.
 */
final readonly class ShoutRepository
{
    public function __construct(private ModuleDb $db)
    {
    }

    /**
     * @return list<array{id:int,message:string,created_at:string,source:string}>
     */
    public function latest(int $limit = 100): array
    {
        return $this->db->table('messages')
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit(max(1, min(500, $limit)))
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'message' => (string)$row->message,
                'created_at' => (string)$row->created_at,
                'source' => (string)$row->source,
            ])
            ->all();
    }

    public function create(string $message, int $memberId = 0, string $source = 'panel'): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('messages')->insert([
            'member_id' => max(0, $memberId),
            'message' => $message,
            'source' => $source,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function delete(int $id): void
    {
        if ($id > 0) {
            $this->db->table('messages')->where('id', $id)->update([
                'deleted_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
