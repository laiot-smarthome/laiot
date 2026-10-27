<?php

declare(strict_types=1);

namespace MajorDoMo\Events;

use MajorDoMo\Logging\ErrorRepository;
use MajorDoMo\Modules\PropertySubscriberRegistry;
use MajorDoMo\Queue\JobHandler;
use MajorDoMo\Runtime\UserCodeDeadline;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Доставка изменения свойства модулю в процессе его worker.
 *
 * Задание приходит из очереди `module.<id>`; здесь создаётся шаг
 * `module.delivery`, вызывается подписчик и фиксируется результат. Модуль
 * получает контекст своей цепочки, поэтому вложенная запись сохраняет
 * защиту от цикла и гасит собственное эхо.
 *
 * Ошибка подписчика останавливает оставшиеся шаги этой цепочки; независимые
 * команды продолжаются. Вложенные записи ждут завершения своего родителя.
 *
 * 2026-10-27 — доставка участвует в долговечном выполнении цепочки.
 * 2026-10-27 — обработчик создан вместе с изоляцией модульных очередей.
 */
final readonly class ModulePropertyHandler implements JobHandler
{
    public function __construct(
        private PropertySubscriberRegistry $subscribers,
        private PropertyEventRepository $events,
        private PropertyEventContext $context,
        private LoggerInterface $logger,
        private PropertyExecutionRepository $executions,
        private ErrorRepository $errors,
        private UserCodeDeadline $deadline
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload): void
    {
        $executionId = (int)($payload['execution_id'] ?? 0);
        $step = $this->executions->find($executionId);
        if (in_array($step['status'], ['ok', 'skipped', 'error'], true)) {
            return;
        }
        $cancelled = $this->executions->cancelled($executionId);
        $module = is_string($payload['module'] ?? null) ? $payload['module'] : '';
        if ($module === '') {
            $this->executions->fail($executionId, 'Не указан модуль-получатель.');
            return;
        }

        $objectId = max(0, (int)($payload['object_id'] ?? 0));
        $propertyId = max(0, (int)($payload['property_id'] ?? 0));
        $objectTitle = (string)($payload['object_title'] ?? '');
        $propertyTitle = (string)($payload['property_title'] ?? '');
        $oldValue = (string)($payload['old_value'] ?? '');
        $newValue = (string)($payload['new_value'] ?? '');
        $source = (string)($payload['source'] ?? '');
        $changed = (bool)($payload['changed'] ?? false);
        $chain = $this->chain($payload['chain'] ?? []);
        $depth = max(0, (int)($payload['depth'] ?? 0));
        $parentId = max(0, (int)($payload['parent_id'] ?? 0));

        $eventId = $this->executions->journal($executionId, 'handler_event_id', fn (): int => $this->events->record([
            'parent_id' => $parentId,
            'type' => 'module.delivery',
            'status' => $cancelled ? 'skipped' : 'running',
            'message' => $cancelled ? 'Цепочка остановлена после ошибки другого шага.' : null,
            'source' => $source,
            'object_id' => $objectId,
            'property_id' => $propertyId,
            'object_title' => $objectTitle,
            'property_title' => $propertyTitle,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'handler' => $module,
            'chain' => $chain,
            'depth' => $depth + 1,
        ]));
        if ($cancelled) {
            $this->executions->update($executionId, ['status' => 'skipped']);
            return;
        }
        $this->executions->update($executionId, ['status' => 'running']);

        try {
            $subscriber = $this->subscribers->get($module);
        } catch (Throwable $error) {
            $this->events->finish($eventId, 'error', $error->getMessage());
            $this->failure($executionId, $eventId, $module, $error);
            $this->logger->warning('Подписчик свойства не создан', [
                'module' => $module,
                'error' => $error->getMessage(),
            ]);

            return;
        }

        if ($subscriber === null) {
            $this->events->finish($eventId, 'missing', 'Модуль не зарегистрировал обработчик: ' . $module);
            $this->failure($executionId, $eventId, $module, new \RuntimeException('Модуль не зарегистрировал обработчик: ' . $module));
            $this->logger->warning('Модуль-подписчик не найден', [
                'object' => $objectTitle,
                'property' => $propertyTitle,
                'module' => $module,
            ]);

            return;
        }

        $change = new PropertyChange(
            $parentId,
            $objectId,
            $propertyId,
            $objectTitle,
            $propertyTitle,
            $oldValue,
            $newValue,
            $source,
            $changed,
            $chain,
            $depth + 1
        );

        $snapshot = $this->context->snapshot();
        $this->context->enter($chain, $eventId, $module, $executionId);

        try {
            $this->deadline->run(fn () => $subscriber->onPropertyChanged($change));
            $this->events->finish($eventId, 'ok');
            $this->executions->update($executionId, ['status' => 'ok', 'handler_done' => true]);
        } catch (Throwable $error) {
            $this->events->finish($eventId, 'error', $error->getMessage());
            $this->failure($executionId, $eventId, $module, $error);
            $this->logger->warning('Подписчик свойства завершился ошибкой', [
                'object' => $objectTitle,
                'property' => $propertyTitle,
                'module' => $module,
                'error' => $error->getMessage(),
            ]);
        } finally {
            $this->context->restore($snapshot);
        }
    }

    private function failure(int $executionId, int $eventId, string $module, Throwable $error): void
    {
        $this->executions->fail($executionId, $error->getMessage());
        $this->errors->record('error', 'Ошибка привязки: ' . $error->getMessage(), [
            'exception' => $error, 'execution_id' => $executionId,
            'event_id' => $eventId, 'module' => $module,
        ], '');
    }

    /**
     * @param mixed $chain
     * @return list<string>
     */
    private function chain(mixed $chain): array
    {
        if (!is_array($chain)) {
            return [];
        }

        $tokens = [];
        foreach ($chain as $token) {
            if (is_string($token) && $token !== '' && !in_array($token, $tokens, true)) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }
}
