<?php

declare(strict_types=1);

namespace LaIOT\Services\Notifications;

use Illuminate\Contracts\Validation\Factory;
use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Events\SystemEvents;
use RuntimeException;
use Throwable;

/**
 * Сообщение системы: запись в журнал и фоновая доставка подписчикам.
 *
 * 2026-10-29 — важность фильтрует доставку; источник определяется автоматически.
 * 2026-10-29 — уведомление и задания system.say сохраняются атомарно.
 */
final readonly class SayService
{
    public const EVENT = 'system.say';

    public function __construct(
        private NotificationService $notifications,
        private DatabaseManager $database,
        private SystemEvents $events,
        private SaySource $source,
        Factory $validation,
        private int $minimumImportance
    ) {
        $validation->make(
            ['minimum_importance' => $minimumImportance],
            ['minimum_importance' => ['required', 'integer', 'between:0,10']],
            [],
            ['minimum_importance' => 'Минимальная важность say']
        )->validate();
    }

    /** @return array{status:bool,id?:int,error?:string} */
    public function say(string $message, int $importance = 5): array
    {
        $source = $this->source->current();
        try {
            return $this->database->connection()->transaction(function () use ($message, $importance, $source): array {
                $result = $this->notifications->send($source, $message, 'info', [], $importance);
                if (!$result['status'] || $importance < $this->minimumImportance) {
                    return $result;
                }

                $row = $this->database->connection()->table('notifications')->find($result['id']);
                if ($row === null) {
                    throw new RuntimeException('Сохранённое оповещение не найдено.');
                }

                // Передаём сохранённый текст; получатели выполняются в своих
                // очередях, а ошибка постановки откатывает и запись журнала.
                $this->events->emit(self::EVENT, [
                    'id' => (int)$row->id,
                    'source' => (string)$row->module,
                    'message' => (string)$row->message,
                    'type' => (string)$row->type,
                    'importance' => (int)$row->importance,
                    'created_at' => (string)$row->created_at,
                ]);

                return $result;
            });
        } catch (Throwable $error) {
            return ['status' => false, 'error' => 'Не удалось сохранить оповещение: ' . $error->getMessage()];
        }
    }
}
