<?php

declare(strict_types=1);

namespace MajorDoMo\Audit;

use MajorDoMo\Database\DatabaseManager;
use Throwable;

/**
 * Запись действий в `action_log`. Ошибка аудита не ломает действие.
 */
final readonly class ActionLogger
{
    public function __construct(private DatabaseManager $database)
    {
    }

    public function log(string $type, string $title, string $user = '', string $terminal = 'web', string $ip = ''): void
    {
        if (!$this->database->isConfigured()) {
            return;
        }

        try {
            $now = date('Y-m-d H:i:s');
            $this->database->connection()->table('action_log')->insert([
                'type' => mb_substr($type, 0, 100),
                'title' => mb_substr($title, 0, 255),
                'user_name' => mb_substr($user, 0, 255),
                'terminal' => mb_substr($terminal, 0, 255),
                'ip' => mb_substr($ip, 0, 100),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        } catch (Throwable) {
            // Аудит не должен ломать основной сценарий.
        }
    }
}
