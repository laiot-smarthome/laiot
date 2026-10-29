<?php

declare(strict_types=1);

namespace LaIOT\Services\Panel;

use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Notifications\NotificationService;
use LaIOT\Services\Queue\Queue;
use LaIOT\Support\Clock;
use Throwable;

/**
 * Счётчики внимания для бокового меню.
 *
 * 2026-10-27 — сервис создан вместе с бейджами меню: ошибки, сбойные задания
 * очереди и непрочитанные уведомления показываются рядом с разделами, а не
 * только на своих страницах. Каждый запрос защищён проверкой таблицы, чтобы
 * свежая установка без миграций открывала панель, а не 500.
 *
 * Это оболочка панели, а не доменная логика: сервис только считает записи
 * ядровых репозиториев и ничего не меняет.
 */
final readonly class PanelAttention
{
    public function __construct(
        private DatabaseManager $database,
        private Queue $queue,
        private NotificationService $notifications,
        private Clock $clock
    ) {
    }

    /**
     * Все счётчики, включая нулевые: слот бейджа существует в разметке всегда,
     * иначе при появлении первой ошибки его пришлось бы создавать в JS.
     *
     * @return array<string,array{count:int,tone:string,title:string}>
     */
    public function badges(): array
    {
        $errors = $this->errorsLastDay();
        $jobs = $this->failedJobs();
        $summary = $this->notifications->summary();

        return [
            'system_errors' => [
                'count' => $errors,
                'tone' => 'danger',
                'title' => 'Ошибок за последние сутки: ' . $errors,
            ],
            'jobs' => [
                'count' => $jobs,
                'tone' => 'warning',
                'title' => 'Заданий с ошибкой в очереди: ' . $jobs,
            ],
            'notifications' => [
                'count' => $summary['total'],
                'tone' => $this->severityTone($summary['severity']),
                'title' => 'Непрочитанных уведомлений: ' . $summary['total'],
            ],
        ];
    }

    /**
     * Ошибки за сутки: общий счётчик копился бы годами и перестал быть
     * сигналом «что-то случилось сейчас». Полная история — на /errors.
     */
    private function errorsLastDay(): int
    {
        try {
            $connection = $this->database->connection();
            if (!$connection->getSchemaBuilder()->hasTable('system_errors')) {
                return 0;
            }

            return (int)$connection->table('system_errors')
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $this->clock->now()->subDay()->format('Y-m-d H:i:s'))
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function failedJobs(): int
    {
        try {
            return $this->queue->stats('default')['failed'];
        } catch (Throwable) {
            return 0;
        }
    }

    private function severityTone(string $severity): string
    {
        return match ($severity) {
            'danger' => 'danger',
            'warning' => 'warning',
            'success' => 'success',
            default => 'info',
        };
    }
}
