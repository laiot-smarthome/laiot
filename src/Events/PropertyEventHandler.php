<?php

declare(strict_types=1);

namespace MajorDoMo\Events;

use MajorDoMo\History\HistoryRepository;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Methods\MethodRunner;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertySubscriptionRepository;
use MajorDoMo\Queue\JobHandler;
use MajorDoMo\Queue\Queue;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Обработчик задания `property.changed` в процессе `core-worker events`.
 *
 * Порядок: якорь в журнале → OnChange-метод → история → задания доставки
 * модулям. Значение уже сохранено записавшим процессом, поэтому здесь нет
 * записи значения: только реакция, учёт и постановка доставки.
 *
 * Код модуля здесь не выполняется: каждому подписчику уходит задание в
 * очередь `module.<id>`, которую обслуживает worker этого модуля. Медленный
 * модуль задерживает только свою очередь, а не события свойств.
 *
 * Ошибка метода не откатывает значение: она фиксируется в журнале и логе.
 *
 * 2026-10-27 — доставка модулям вынесена в их собственные очереди.
 * 2026-10-27 — обработчик создан для асинхронного конвейера свойств.
 */
final readonly class PropertyEventHandler implements JobHandler
{
    public function __construct(
        private ObjectRepository $objects,
        private PropertyRepository $properties,
        private HistoryRepository $history,
        private MethodRepository $methods,
        private MethodRunner $methodRunner,
        private PropertyEventRepository $events,
        private PropertySubscriptionRepository $subscriptions,
        private PropertyEventQueue $eventQueues,
        private Queue $queue,
        private PropertyEventContext $context,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload): void
    {
        $objectId = max(0, (int)($payload['object_id'] ?? 0));
        $propertyId = max(0, (int)($payload['property_id'] ?? 0));
        $object = $this->objects->find($objectId);
        $property = $this->properties->find($propertyId);
        if ($object === null || $property === null
            || !$this->properties->isApplicableToObject($property, $object)) {
            $this->logger->info('Событие свойства пропущено: свойство больше не действует', [
                'object_id' => $objectId,
                'property_id' => $propertyId,
            ]);

            return;
        }

        $source = (string)($payload['source'] ?? '');
        $oldValue = (string)($payload['old_value'] ?? '');
        $newValue = (string)($payload['new_value'] ?? '');
        $changed = (bool)($payload['changed'] ?? false);
        $stopped = (bool)($payload['stopped'] ?? false);
        $loop = (bool)($payload['loop'] ?? false);
        $chain = $this->chain($payload['chain'] ?? []);
        $depth = max(0, (int)($payload['depth'] ?? 0));
        $parentId = max(0, (int)($payload['parent_id'] ?? 0));
        $writerModule = is_string($payload['module'] ?? null) && $payload['module'] !== ''
            ? $payload['module'] : null;
        $occurredAt = is_string($payload['occurred_at'] ?? null) && $payload['occurred_at'] !== ''
            ? $payload['occurred_at'] : date('Y-m-d H:i:s');

        $objectTitle = (string)$object['title'];
        $propertyTitle = (string)$property['title'];
        $handler = trim((string)$property['on_change']);

        // Якорь создаётся до метода: вложенные записи должны получить
        // правильного родителя, а зависший обработчик — остаться заметным.
        $eventId = $changed ? $this->events->record([
            'parent_id' => $parentId,
            'type' => 'property.changed',
            'status' => $stopped ? 'stopped' : 'changed',
            'source' => $source,
            'object_id' => $objectId,
            'property_id' => $propertyId,
            'object_title' => $objectTitle,
            'property_title' => $propertyTitle,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'message' => $stopped ? $this->stopMessage($loop) : null,
            'chain' => $chain,
            'depth' => $depth,
        ]) : 0;
        $anchorId = $eventId > 0 ? $eventId : $parentId;

        if ($stopped) {
            if ($handler !== '') {
                $this->events->record([
                    'parent_id' => $anchorId,
                    'type' => 'handler.method',
                    'status' => 'skipped',
                    'source' => $source,
                    'object_id' => $objectId,
                    'property_id' => $propertyId,
                    'object_title' => $objectTitle,
                    'property_title' => $propertyTitle,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'handler' => $handler,
                    'message' => $this->stopMessage($loop),
                    'chain' => $chain,
                    'depth' => $depth + 1,
                ]);
            }
            $this->recordHistory($objectId, $propertyId, $newValue, $changed, $property, $occurredAt);

            return;
        }

        if ($handler !== '') {
            $this->runHandler(
                $object,
                $property,
                $handler,
                $oldValue,
                $newValue,
                $source,
                $chain,
                $depth,
                $anchorId
            );
        }

        // История пишется после реакции, но с временем самой записи значения.
        $this->recordHistory($objectId, $propertyId, $newValue, $changed, $property, $occurredAt);

        $this->deliverModules(
            $object,
            $property,
            $oldValue,
            $newValue,
            $source,
            $changed,
            $chain,
            $depth,
            $anchorId,
            $writerModule
        );
    }

    /**
     * @param array{id:int,title:string,class_id:int,location_id:int,description:string} $object
     * @param array{id:int,class_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string} $property
     * @param list<string> $chain
     */
    private function runHandler(
        array $object,
        array $property,
        string $handler,
        string $oldValue,
        string $newValue,
        string $source,
        array $chain,
        int $depth,
        int $parentId
    ): void {
        $objectTitle = (string)$object['title'];
        $propertyTitle = (string)$property['title'];

        $handlerEventId = $this->events->record([
            'parent_id' => $parentId,
            'type' => 'handler.method',
            'status' => 'running',
            'source' => $source,
            'object_id' => (int)$object['id'],
            'property_id' => (int)$property['id'],
            'object_title' => $objectTitle,
            'property_title' => $propertyTitle,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'handler' => $handler,
            'chain' => $chain,
            'depth' => $depth + 1,
        ]);

        $snapshot = $this->context->snapshot();
        $this->context->enter($chain, $handlerEventId, null);

        try {
            if ($this->methods->findForObject((int)$object['id'], (int)$object['class_id'], $handler) === null) {
                $this->events->finish($handlerEventId, 'missing', 'Метод не найден: ' . $handler);
                $this->logger->warning('OnChange-метод свойства не найден', [
                    'object' => $objectTitle,
                    'property' => $propertyTitle,
                    'handler' => $handler,
                ]);

                return;
            }

            $this->methodRunner->run($objectTitle, $handler, [
                'OBJECT' => $objectTitle,
                'PROPERTY' => $propertyTitle,
                'OLD_VALUE' => $oldValue,
                'NEW_VALUE' => $newValue,
                'SOURCE' => $source,
                'DEPTH' => $depth + 1,
                'CHAIN' => $chain,
            ]);
            $this->events->finish($handlerEventId, 'ok');
        } catch (Throwable $error) {
            $this->events->finish($handlerEventId, 'error', $error->getMessage());
            $this->logger->warning('OnChange-обработчик свойства завершился ошибкой', [
                'object' => $objectTitle,
                'property' => $propertyTitle,
                'handler' => $handler,
                'error' => $error->getMessage(),
            ]);
        } finally {
            $this->context->restore($snapshot);
        }
    }

    /**
     * @param array{id:int,title:string,class_id:int,location_id:int,description:string} $object
     * @param array{id:int,class_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string} $property
     * @param list<string> $chain
     */
    private function deliverModules(
        array $object,
        array $property,
        string $oldValue,
        string $newValue,
        string $source,
        bool $changed,
        array $chain,
        int $depth,
        int $parentId,
        ?string $writerModule
    ): void {
        $objectId = (int)$object['id'];
        $propertyId = (int)$property['id'];
        $modules = $this->subscriptions->modulesFor($objectId, $propertyId);
        if ($modules === []) {
            return;
        }

        $objectTitle = (string)$object['title'];
        $propertyTitle = (string)$property['title'];

        foreach ($modules as $module) {
            // Эхо автора: модуль уже сам записал это значение.
            if ($writerModule !== null && $module === $writerModule) {
                continue;
            }

            $this->queue->pushJob(
                new ModulePropertyChangedJob([
                    'module' => $module,
                    'parent_id' => $parentId,
                    'object_id' => $objectId,
                    'property_id' => $propertyId,
                    'object_title' => $objectTitle,
                    'property_title' => $propertyTitle,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'source' => $source,
                    'changed' => $changed,
                    'chain' => $chain,
                    'depth' => $depth,
                ]),
                $this->eventQueues->moduleQueueName($module)
            );
        }
    }

    /**
     * @param array{id:int,class_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string} $property
     */
    private function recordHistory(
        int $objectId,
        int $propertyId,
        string $newValue,
        bool $changed,
        array $property,
        string $occurredAt
    ): void {
        if (!$changed || !$property['keep_history']) {
            return;
        }

        $this->history->record($objectId, $propertyId, $newValue, $occurredAt);
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

    private function stopMessage(bool $loop): string
    {
        return $loop
            ? 'Свойство уже встречалось в цепочке: обработчик не вызван, значение сохранено.'
            : 'Цепочка достигла предела ' . PropertyEventContext::MAX_DEPTH
                . ' шагов: обработчик не вызван, значение сохранено.';
    }
}
