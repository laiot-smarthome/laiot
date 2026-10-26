<?php

declare(strict_types=1);

use MajorDoMo\Methods\MethodRunner;
use MajorDoMo\Notifications\NotificationService;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Runtime\TimeoutRepository;
use MajorDoMo\Support\AppContext;

if (!function_exists('getObject')) {
    /**
     * @return array{id:int,title:string,class_id:int,location_id:int,description:string}|null
     */
    function getObject(string $name): ?array
    {
        return AppContext::get()?->container()->make(ObjectRepository::class)->findByTitle(trim($name));
    }
}

if (!function_exists('gg')) {
    /**
     * `gg('Object.Property')` — значение свойства объекта.
     *
     * Свойство ищется в классе объекта, затем среди глобальных (`class_id = 0`).
     */
    function gg(string $name): ?string
    {
        $app = AppContext::get();
        if ($app === null) {
            return null;
        }
        [$objectName, $propertyName] = split_object_property($name);
        if ($objectName === '' || $propertyName === '') {
            return null;
        }

        return $app->container()->make(PropertyValueService::class)->get($objectName, $propertyName);
    }
}

if (!function_exists('sg')) {
    /**
     * `sg('Object.Property', $value, $noLinked, $source)` — записать значение.
     *
     * Значение сохраняется, пишется история (если свойство её хранит), а затем
     * синхронно вызывается OnChange-метод свойства. `$noLinked` оставлен для
     * совместимости сигнатуры alpha: в MyDoMo подписок `LINKED_MODULES` нет.
     *
     * @param mixed $value Скаляр, null или массив (массив сохраняется как JSON).
     * @param int|array<string, int> $noLinked Не используется; сохранён для alpha-совместимости.
     */
    function sg(string $name, $value, $noLinked = 0, string $source = 'sg'): bool
    {
        $app = AppContext::get();
        if ($app === null) {
            return false;
        }
        [$objectName, $propertyName] = split_object_property($name);
        if ($objectName === '' || $propertyName === '') {
            return false;
        }

        return $app->container()->make(PropertyValueService::class)
            ->set($objectName, $propertyName, $value, $source);
    }
}

if (!function_exists('notify')) {
    /**
     * `notify()` — системное уведомление в колокольчике админки.
     *
     * @param array{key?:string,title?:string,details?:string,url?:string,label?:string} $options
     * @return array{status:bool,id?:int,error?:string}
     */
    function notify(string $message, string $type = 'info', array $options = []): array
    {
        $app = AppContext::get();
        if ($app === null) {
            return ['status' => false, 'error' => 'Приложение не загружено.'];
        }

        return $app->container()->make(NotificationService::class)
            ->send('system', $message, $type, $options);
    }
}

if (!function_exists('callMethod')) {
    /**
     * @param array<string, mixed> $params
     */
    function callMethod(string $object, string $method, array $params = []): mixed
    {
        $app = AppContext::get();
        if ($app === null) {
            return null;
        }

        return $app->container()->make(MethodRunner::class)->run($object, $method, $params);
    }
}

if (!function_exists('setTimeOut')) {
    function setTimeOut(string $title, string $commands, int $timeout = 0, int $expire = 0): int
    {
        $app = AppContext::get();
        if ($app === null) {
            return 0;
        }

        return $app->container()->make(TimeoutRepository::class)->set($title, $commands, $timeout);
    }
}

if (!function_exists('clearTimeOut')) {
    function clearTimeOut(string $title): bool
    {
        $app = AppContext::get();

        return $app !== null && $app->container()->make(TimeoutRepository::class)->clear($title);
    }
}

if (!function_exists('getHistory')) {
    /**
     * @return list<array{value:string,created_at:string}>
     */
    function getHistory(string $name, int $limit = 50): array
    {
        $app = AppContext::get();
        if ($app === null) {
            return [];
        }
        [$objectName, $propertyName] = split_object_property($name);
        if ($objectName === '' || $propertyName === '') {
            return [];
        }

        return $app->container()->make(PropertyValueService::class)
            ->history($objectName, $propertyName, $limit);
    }
}

if (!function_exists('split_object_property')) {
    /**
     * @return array{0:string,1:string}
     */
    function split_object_property(string $name): array
    {
        $name = trim($name);
        $dot = strrpos($name, '.');
        if ($dot === false) {
            return ['', ''];
        }

        return [substr($name, 0, $dot), substr($name, $dot + 1)];
    }
}
