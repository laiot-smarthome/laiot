<?php

declare(strict_types=1);

namespace MajorDoMo\History;

use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\ValueRepository;
use MajorDoMo\Support\Clock;

/**
 * Данные истории свойства для UI: текущее значение, статистика, график и записи.
 *
 * Вынесено из контроллера, чтобы один и тот же расчёт использовали Yoyo-блок
 * и любые будущие страницы. Диапазоны считаются здесь, а не в JS.
 *
 * 2026-10-27 — сервис создан при переносе истории на Yoyo-компонент.
 */
final readonly class PropertyHistoryService
{
    private const RANGES = ['24h', '7d', '30d', 'all'];

    private const MAX_POINTS = 400;

    public function __construct(
        private HistoryRepository $history,
        private ObjectRepository $objects,
        private PropertyRepository $properties,
        private ValueRepository $values,
        private Clock $clock
    ) {
    }

    /**
     * @return array<string, mixed>|null null, если объект или свойство не найдены
     */
    public function for(int $objectId, int $propertyId, string $range = '24h'): ?array
    {
        $range = in_array($range, self::RANGES, true) ? $range : '24h';
        $object = $this->objects->find($objectId);
        $property = $this->properties->find($propertyId);
        if ($object === null || $property === null) {
            return null;
        }

        $rows = $this->history->forValue($objectId, $propertyId, 1000);
        $cutoff = match ($range) {
            '24h' => $this->clock->now()->subDay()->format('Y-m-d H:i:s'),
            '7d' => $this->clock->now()->subDays(7)->format('Y-m-d H:i:s'),
            '30d' => $this->clock->now()->subDays(30)->format('Y-m-d H:i:s'),
            default => '',
        };
        if ($cutoff !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => $row['created_at'] >= $cutoff
            ));
        }

        $numeric = $this->isNumericSeries($rows, $property['data_type']);

        $chart = [];
        if ($numeric) {
            foreach (array_reverse(array_slice($rows, 0, self::MAX_POINTS)) as $row) {
                $chart[] = [
                    't' => (strtotime($row['created_at']) ?: 0) * 1000,
                    'v' => (float)$row['value'],
                ];
            }
        }

        return [
            'object' => ['id' => $object['id'], 'title' => $object['title']],
            'property' => [
                'id' => $property['id'],
                'title' => $property['title'],
                'data_type' => $property['data_type'],
            ],
            'current' => $this->values->get($objectId, $propertyId) ?? '',
            'is_numeric' => $numeric,
            'range' => $range,
            'stats' => $this->stats($rows, $numeric),
            'chart' => $chart,
            'history' => array_slice($rows, 0, 200),
        ];
    }

    /**
     * @param list<array{value:string,created_at:string}> $rows
     */
    private function isNumericSeries(array $rows, string $dataType): bool
    {
        if (in_array($dataType, ['int', 'float'], true)) {
            return true;
        }

        foreach ($rows as $row) {
            if ($row['value'] === '' || !is_numeric($row['value'])) {
                return false;
            }
        }

        return $rows !== [];
    }

    /**
     * @param list<array{value:string,created_at:string}> $rows
     * @return array<string, int|float|string|null>
     */
    private function stats(array $rows, bool $numeric): array
    {
        $count = count($rows);
        if ($count === 0) {
            return ['count' => 0, 'min' => null, 'max' => null, 'avg' => null, 'last' => null];
        }

        $last = $rows[0]['value'];
        if (!$numeric) {
            return ['count' => $count, 'min' => null, 'max' => null, 'avg' => null, 'last' => $last];
        }

        $values = [];
        foreach ($rows as $row) {
            if ($row['value'] !== '' && is_numeric($row['value'])) {
                $values[] = (float)$row['value'];
            }
        }
        if ($values === []) {
            return ['count' => $count, 'min' => null, 'max' => null, 'avg' => null, 'last' => $last];
        }

        return [
            'count' => $count,
            'min' => min($values),
            'max' => max($values),
            'avg' => round(array_sum($values) / count($values), 2),
            'last' => $last,
        ];
    }
}
