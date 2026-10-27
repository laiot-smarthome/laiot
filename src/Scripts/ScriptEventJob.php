<?php

declare(strict_types=1);

namespace MajorDoMo\Scripts;

use MajorDoMo\Queue\JobHandler;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Выполняет сценарий, привязанный к системному событию.
 *
 * Задание приходит из очереди `default`. Сценарий получает
 * `$params['event']` и `$params['data']`. Ошибка сценария не возвращается в
 * очередь: повтор каждую минуту только засорил бы failed-задания, поэтому она
 * пишется в лог ядра, а следующее событие запускает сценарий снова.
 *
 * 2026-10-27 — запуск сценария по системному событию.
 */
final readonly class ScriptEventJob implements JobHandler
{
    public function __construct(
        private ScriptRepository $scripts,
        private ScriptRunner $runner,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload): void
    {
        $id = max(0, (int)($payload['id'] ?? 0));
        $event = (string)($payload['event'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        // Сценарий могли удалить между событием и обработкой задания: это не
        // ошибка очереди, задание просто пропускается.
        if ($id <= 0 || $this->scripts->find($id) === null) {
            return;
        }

        try {
            $this->runner->run($id, ['event' => $event, 'data' => $data]);
        } catch (Throwable $error) {
            $this->logger->error('Сценарий по системному событию завершился ошибкой', [
                'id' => $id,
                'title' => (string)($payload['title'] ?? ''),
                'event' => $event,
                'error' => $error->getMessage(),
            ]);
        }
    }
}
