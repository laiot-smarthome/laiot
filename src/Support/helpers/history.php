<?php

declare(strict_types=1);

use MajorDoMo\History\HistoryQuery;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Support\AppContext;

if (!function_exists('history')) {
    /**
     * Построитель выборки истории: `history('Кухня.temperature')->from('-1 day')->avg()`.
     *
     * Возвращает null, если объект/свойство не найдены или приложение не
     * загружено; цепочка на null не падает.
     */
    function history(string $name): ?HistoryQuery
    {
        $app = AppContext::get();
        if ($app === null) {
            return null;
        }
        [$objectName, $propertyName] = split_object_property($name);
        if ($objectName === '' || $propertyName === '') {
            return null;
        }

        return $app->container()->make(PropertyValueService::class)
            ->query($objectName, $propertyName);
    }
}

if (!function_exists('getHistoryRange')) {
    /**
     * Записи истории за период; `$from`/`$to` — дата или относительная строка.
     *
     * @return list<array{value:string,created_at:string}>
     */
    function getHistoryRange(
        string $name,
        string|DateTimeInterface|null $from = null,
        string|DateTimeInterface|null $to = null,
        int $limit = 500
    ): array {
        return history($name)?->from($from)->to($to)->last($limit)->values() ?? [];
    }
}

if (!function_exists('getHistoryAvg')) {
    function getHistoryAvg(
        string $name,
        string|DateTimeInterface|null $from = null,
        string|DateTimeInterface|null $to = null
    ): ?float {
        return history($name)?->from($from)->to($to)->avg();
    }
}

if (!function_exists('getHistoryMin')) {
    function getHistoryMin(
        string $name,
        string|DateTimeInterface|null $from = null,
        string|DateTimeInterface|null $to = null
    ): ?float {
        return history($name)?->from($from)->to($to)->min();
    }
}

if (!function_exists('getHistoryMax')) {
    function getHistoryMax(
        string $name,
        string|DateTimeInterface|null $from = null,
        string|DateTimeInterface|null $to = null
    ): ?float {
        return history($name)?->from($from)->to($to)->max();
    }
}

if (!function_exists('getHistorySum')) {
    function getHistorySum(
        string $name,
        string|DateTimeInterface|null $from = null,
        string|DateTimeInterface|null $to = null
    ): ?float {
        return history($name)?->from($from)->to($to)->sum();
    }
}

if (!function_exists('getHistoryCount')) {
    function getHistoryCount(
        string $name,
        string|DateTimeInterface|null $from = null,
        string|DateTimeInterface|null $to = null
    ): int {
        return history($name)?->from($from)->to($to)->count() ?? 0;
    }
}
