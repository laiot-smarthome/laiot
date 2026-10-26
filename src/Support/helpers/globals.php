<?php

declare(strict_types=1);

use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\ValueRepository;
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

        $object = $app->container()->make(ObjectRepository::class)->findByTitle($objectName);
        $property = $app->container()->make(PropertyRepository::class)->findByTitle($propertyName);
        if ($object === null || $property === null) {
            return null;
        }

        return $app->container()->make(ValueRepository::class)->get($object['id'], $property['id']);
    }
}

if (!function_exists('sg')) {
    /**
     * `sg('Object.Property', $value)` — записать значение свойства объекта.
     *
     * @param mixed $value
     */
    function sg(string $name, $value): bool
    {
        $app = AppContext::get();
        if ($app === null || !is_scalar($value)) {
            return false;
        }
        [$objectName, $propertyName] = split_object_property($name);
        if ($objectName === '' || $propertyName === '') {
            return false;
        }

        $object = $app->container()->make(ObjectRepository::class)->findByTitle($objectName);
        $property = $app->container()->make(PropertyRepository::class)->findByTitle($propertyName);
        if ($object === null || $property === null) {
            return false;
        }

        $app->container()->make(ValueRepository::class)->set($object['id'], $property['id'], (string)$value);

        return true;
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
