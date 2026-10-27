<?php

declare(strict_types=1);

namespace MajorDoMo\Events;

use MajorDoMo\Modules\PropertySubscriberRegistry;
use MajorDoMo\Queue\JobHandler;
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
 * Ошибка подписчика не роняет очередь модуля и не мешает другим модулям:
 * она записывается в журнал и лог, значение уже сохранено.
 *
 * 2026-10-27 — обработчик создан вместе с изоляцией модульных очередей.
 */
final readonly class ModulePropertyHandler implements JobHandler
{
    public function __construct(
        private PropertySubscriberRegistry $subscribers,
        private PropertyEventRepository $events,
        private PropertyEventContext $context,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload): void
    {
        $module = is_string($payload['module'] ?? null) ? $payload['module'] : '';
        if ($module === '') {
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

        $eventId = $this->events->record([
            'parent_id' => $parentId,
            'type' => 'module.delivery',
            'status' => 'running',
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
        ]);

        try {
            $subscriber = $this->subscribers->get($module);
        } catch (Throwable $error) {
            $this->events->finish($eventId, 'error', $error->getMessage());
            $this->logger->warning('Подписчик свойства не создан', [
                'module' => $module,
                'error' => $error->getMessage(),
            ]);

            return;
        }

        if ($subscriber === null) {
            $this->events->finish($eventId, 'missing', 'Модуль не зарегистрировал обработчик: ' . $module);
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
        $this->context->enter($chain, $eventId, $module);

        try {
            $subscriber->onPropertyChanged($change);
            $this->events->finish($eventId, 'ok');
        } catch (Throwable $error) {
            $this->events->finish($eventId, 'error', $error->getMessage());
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
