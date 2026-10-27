<?php

declare(strict_types=1);

use MajorDoMo\Methods\MethodRunner;
use MajorDoMo\Notifications\NotificationService;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Runtime\TimeoutRepository;
use MajorDoMo\Scripts\ScriptRunner;
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
     * Свойство ищется у объекта, затем в его классе и по цепочке наследования.
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

if (!function_exists('getGlobal')) {
    /**
     * `getGlobal('Object.Property')` — alpha-имя для `gg()`.
     */
    function getGlobal(string $name): ?string
    {
        return gg($name);
    }
}

if (!function_exists('sg')) {
    /**
     * `sg('Object.Property', $value, $source)` — записать значение.
     *
     * Значение сохраняется, пишется история (если свойство её хранит), затем
     * вызываются подписанные модули и OnChange-метод свойства. Модуль, который
     * сам записал значение, уведомление о нём не получает — это эхо, а не
     * событие.
     *
     * @param mixed $value Скаляр, null или массив (массив сохраняется как JSON).
     */
    function sg(string $name, $value, string $source = 'sg'): bool
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

if (!function_exists('setGlobal')) {
    /**
     * `setGlobal()` — alpha-имя для `sg()`.
     *
     * @param mixed $value Скаляр, null или массив (массив сохраняется как JSON).
     */
    function setGlobal(string $name, $value, string $source = ''): bool
    {
        return sg($name, $value, $source !== '' ? $source : 'setGlobal');
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
     * Вызвать метод объекта.
     *
     * Поддерживаются обе формы:
     *   callMethod('Кухня', 'double', ['x' => 5]); // MyDoMo
     *   callMethod('Кухня.double', ['x' => 5]);    // alpha
     *
     * @param mixed $method Имя метода или массив параметров для формы «Объект.метод».
     * @param array<string, mixed> $params
     */
    function callMethod(string $object, mixed $method = null, array $params = []): mixed
    {
        $app = AppContext::get();
        if ($app === null) {
            return null;
        }

        if (is_array($method) && $params === []) {
            $params = $method;
            $method = null;
        }
        if (!is_string($method) || trim($method) === '') {
            [$objectName, $methodName] = split_object_property($object);
            if ($objectName === '' || $methodName === '') {
                return null;
            }
            $object = $objectName;
            $method = $methodName;
        }

        return $app->container()->make(MethodRunner::class)->run(trim($object), trim($method), $params);
    }
}

if (!function_exists('cm')) {
    /**
     * `cm('Объект.метод', $params)` — короткое имя `callMethod`.
     *
     * @param mixed $params Массив параметров; не-массив приводится к пустому.
     */
    function cm(string $method, mixed $params = 0): mixed
    {
        return callMethod($method, is_array($params) ? $params : []);
    }
}

if (!function_exists('runMethod')) {
    /**
     * @param mixed $params Массив параметров; не-массив приводится к пустому.
     */
    function runMethod(string $method, mixed $params = 0): mixed
    {
        return cm($method, $params);
    }
}

if (!function_exists('callMethodSafe')) {
    /**
     * В alpha «safe» означал отдельный режим; в MyDoMo вызов синхронный и
     * совпадает с `callMethod`.
     *
     * @param mixed $method Имя метода или массив параметров для формы «Объект.метод».
     * @param array<string, mixed> $params
     */
    function callMethodSafe(string $object, mixed $method = null, array $params = []): mixed
    {
        return callMethod($object, $method, $params);
    }
}

if (!function_exists('runScript')) {
    /**
     * Запустить сценарий по ID или названию.
     *
     * @param int|string $id ID сценария или его название.
     * @param mixed $params Массив параметров; не-массив приводится к пустому.
     */
    function runScript(int|string $id, mixed $params = []): mixed
    {
        $app = AppContext::get();
        if ($app === null) {
            return null;
        }

        return $app->container()->make(ScriptRunner::class)->run($id, $params);
    }
}

if (!function_exists('callScript')) {
    /**
     * @param mixed $params Массив параметров; не-массив приводится к пустому.
     */
    function callScript(int|string $id, mixed $params = ''): mixed
    {
        return runScript($id, $params);
    }
}

if (!function_exists('runScriptSafe')) {
    /**
     * В alpha запускал сценарий через безопасную очередь; в MyDoMo вызов
     * синхронный и совпадает с `runScript`.
     *
     * @param mixed $params Массив параметров; не-массив приводится к пустому.
     */
    function runScriptSafe(int|string $id, mixed $params = 0): mixed
    {
        return runScript($id, $params);
    }
}

if (!function_exists('rs')) {
    /**
     * @param mixed $params Массив параметров; не-массив приводится к пустому.
     */
    function rs(int|string $id, mixed $params = 0): mixed
    {
        return runScript($id, $params);
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
