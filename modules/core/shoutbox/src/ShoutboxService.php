<?php

declare(strict_types=1);

namespace MajorDoMo\Modules\Core\Shoutbox;

use MajorDoMo\Support\AppContext;

/**
 * Публичный сервис модуля.
 *
 * Helper-функции и другие модули не лезут в таблицу напрямую: они зовут
 * сервис, а сервис уже решает, как читать/писать данные модуля.
 */
final class ShoutboxService
{
    /**
     * @return list<array{id:int,message:string,created_at:string,source:string}>
     */
    public function latest(int $limit = 10): array
    {
        $db = AppContext::get()?->modules()->db('shoutbox');
        if ($db === null) {
            return [];
        }

        return $db->table('messages')
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'message' => (string)$row->message,
                'created_at' => (string)$row->created_at,
                'source' => (string)$row->source,
            ])
            ->all();
    }
}
