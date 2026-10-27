<?php

declare(strict_types=1);

namespace MajorDoMo\Properties;

use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Events\PropertyChangedJob;
use MajorDoMo\Events\PropertyEventContext;
use MajorDoMo\Events\PropertyEventQueue;
use MajorDoMo\History\HistoryQuery;
use MajorDoMo\History\HistoryRepository;
use MajorDoMo\Objects\ObjectRepository;

/**
 * Единственный путь записи значения свойства в новом ядре.
 *
 * Запись максимально короткая: сохранить значение и в той же транзакции
 * положить задание `property.changed` в очередь событий. Журнал, история,
 * OnChange-метод и доставка модулям выполняются отдельным процессом
 * `core-worker events` — тысяча одновременных изменений не запускает тысячу
 * пользовательских методов внутри запроса датчика.
 *
 * Задание несёт цепочку свойства, глубину, родителя журнала и модуль-автора,
 * поэтому обработчик продолжает ту же цепочку и не возвращает модулю его
 * собственное эхо. Полоса очереди выбирается по объекту и свойству, поэтому
 * порядок изменений одного свойства сохраняется при нескольких процессах.
 *
 * 2026-10-27 — запись переведена на асинхронный конвейер: значение + задание,
 *              без синхронных OnChange, истории и доставки.
 * 2026-10-27 — параллельные записи объекта согласуют старое значение и событие.
 * 2026-10-27 — поиск свойства идёт по объектным переопределениям и цепочке
 *              наследования классов; чужое/перекрытое свойство не пишется.
 * 2026-10-26 — запись по ID проверяет принадлежность свойства классу объекта.
 */
final class PropertyValueService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly ObjectRepository $objects,
        private readonly PropertyRepository $properties,
        private readonly ValueRepository $values,
        private readonly HistoryRepository $history,
        private readonly PropertyEventContext $context,
        private readonly PropertyEventQueue $events
    ) {
    }

    /**
     * Находит объект и его свойство (в классе объекта, затем глобальное).
     *
     * @return array{
     *     object:array{id:int,title:string,class_id:int,location_id:int,description:string},
     *     property:array{id:int,class_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string}
     * }|null
     */
    public function resolve(string $objectTitle, string $propertyTitle): ?array
    {
        $object = $this->objects->findByTitle(trim($objectTitle));
        if ($object === null) {
            return null;
        }

        $property = $this->properties->resolveForObject((int)$object['id'], $propertyTitle);
        if ($property === null) {
            return null;
        }

        return ['object' => $object, 'property' => $property];
    }

    public function get(string $objectTitle, string $propertyTitle): ?string
    {
        $resolved = $this->resolve($objectTitle, $propertyTitle);

        return $resolved === null
            ? null
            : $this->values->get($resolved['object']['id'], $resolved['property']['id']);
    }

    /**
     * @return list<array{value:string,created_at:string}>
     */
    public function history(string $objectTitle, string $propertyTitle, int $limit = 50): array
    {
        $resolved = $this->resolve($objectTitle, $propertyTitle);

        return $resolved === null
            ? []
            : $this->history->forValue($resolved['object']['id'], $resolved['property']['id'], $limit);
    }

    /**
     * Построитель выборки истории: период, лимит, значения и агрегаты.
     */
    public function query(string $objectTitle, string $propertyTitle): ?HistoryQuery
    {
        $resolved = $this->resolve($objectTitle, $propertyTitle);
        if ($resolved === null) {
            return null;
        }

        return $this->history->query(
            (int)$resolved['object']['id'],
            (int)$resolved['property']['id']
        );
    }

    /**
     * `sg()`-совместимая запись по названиям «Объект.Свойство».
     *
     * @param mixed $value Скаляр, null или массив (массив сохраняется как JSON).
     */
    public function set(string $objectTitle, string $propertyTitle, mixed $value, string $source = 'sg'): bool
    {
        $resolved = $this->resolve($objectTitle, $propertyTitle);
        if ($resolved === null) {
            return false;
        }

        return $this->write($resolved['object'], $resolved['property'], $value, $source);
    }

    /**
     * Запись по идентификаторам: так пишут формы объектов и страница значений.
     */
    public function setValue(int $objectId, int $propertyId, mixed $value, string $source = 'panel'): bool
    {
        $object = $this->objects->find($objectId);
        $property = $this->properties->find($propertyId);
        if ($object === null || $property === null
            || !$this->properties->isApplicableToObject($property, $object)) {
            return false;
        }

        return $this->write($object, $property, $value, $source);
    }

    /**
     * @param array{id:int,title:string,class_id:int,location_id:int,description:string} $object
     * @param array{id:int,class_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string} $property
     */
    private function write(array $object, array $property, mixed $value, string $source): bool
    {
        $newValue = $this->normalize($value);
        if ($newValue === null) {
            return false;
        }

        $objectId = (int)$object['id'];
        $propertyId = (int)$property['id'];
        $objectTitle = (string)$object['title'];
        $propertyTitle = (string)$property['title'];
        $source = mb_substr(trim($source), 0, 191);

        $token = $this->context->token($objectTitle, $propertyTitle);
        $loop = $this->context->contains($token);
        $tooDeep = $this->context->depth() >= PropertyEventContext::MAX_DEPTH;

        // Значение и задание — одна транзакция: событие не потеряется, если
        // запись упадёт, и не появится без сохранённого значения.
        $this->database->transaction(function () use ($objectId, $propertyId, $objectTitle, $propertyTitle, $newValue, $source, $token, $loop, $tooDeep): void {
            // Строка объекта существует и до первого значения свойства, поэтому
            // защищает также одновременное создание отсутствующего значения.
            $this->database->connection()->table('objects')->where('id', $objectId)->lockForUpdate()->first();
            $oldValue = (string)($this->values->get($objectId, $propertyId) ?? '');
            $job = new PropertyChangedJob([
                'object_id' => $objectId,
                'property_id' => $propertyId,
                'object_title' => $objectTitle,
                'property_title' => $propertyTitle,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'source' => $source,
                'changed' => $oldValue !== $newValue,
                'chain' => $this->context->nextChain($token),
                'depth' => $this->context->depth(),
                'stopped' => $loop || $tooDeep,
                'loop' => $loop,
                'parent_id' => $this->context->parentEventId(),
                'module' => $this->context->module(),
                'occurred_at' => date('Y-m-d H:i:s'),
            ]);
            $this->values->write($objectId, $propertyId, $newValue);
            $this->events->push($job, $objectId, $propertyId);
        });

        return true;
    }

    /**
     * Значение приводится один раз, до записи и журнала.
     */
    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return (string)$value;
        }
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

            return $encoded === false ? null : $encoded;
        }

        return null;
    }
}
