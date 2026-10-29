<?php

declare(strict_types=1);

namespace LaIOT\Services\Modules;

use Composer\Semver\Semver;
use InvalidArgumentException;
use JsonException;

/**
 * Манифест модуля LaIOT SDK.
 *
 * `module.json` — единственный контракт модуля: id, namespace, точки входа,
 * маршруты, представления, миграции, storage, menu, settings,
 * requires/dependencies.
 * Никаких legacy-полей `project_modules` здесь нет.
 *
 * 2026-10-27 — необязательная схема `settings` с типами и умолчаниями.
 * 2026-10-27 — пустой settings из toArray() принимается при повторной сборке.
 */
final readonly class ModuleManifest
{
    public const TYPES = ['core', 'module'];

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        public array $data,
        public string $path,
        public string $type
    ) {
    }

    public static function fromFile(string $file, string $path, ?string $expectedType = null): self
    {
        $json = file_get_contents($file);
        if ($json === false) {
            throw new InvalidArgumentException('Не удалось прочитать module.json: ' . $file);
        }
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('module.json не является валидным JSON: ' . $error->getMessage());
        }
        if (!is_array($data)) {
            throw new InvalidArgumentException('module.json должен быть объектом.');
        }

        return self::fromArray($data, $path, $expectedType);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, string $path = '', ?string $expectedType = null): self
    {
        $id = self::string($data, 'id');
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $id)) {
            throw new InvalidArgumentException('id модуля: только [a-z][a-z0-9_], 2–64 символа.');
        }

        $type = strtolower(self::string($data, 'type', $expectedType ?? 'module'));
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('type модуля: core или module.');
        }
        if ($expectedType !== null && $type !== $expectedType) {
            throw new InvalidArgumentException('type модуля не совпадает с каталогом: ожидался ' . $expectedType . '.');
        }

        $namespace = self::string($data, 'namespace');
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_\\\\]*\\\\$/D', $namespace)) {
            throw new InvalidArgumentException('namespace модуля должен заканчиваться обратным слешем.');
        }

        $version = self::string($data, 'version', '0.0.0');
        try {
            (new \Composer\Semver\VersionParser())->normalize($version);
        } catch (\UnexpectedValueException) {
            throw new InvalidArgumentException('version модуля должна быть semver-строкой.');
        }

        $paths = [];
        foreach (['src', 'routes', 'views', 'migrations', 'assets'] as $key) {
            $default = match ($key) {
                'routes' => 'routes.php',
                'views' => 'views',
                'migrations' => 'migrations',
                default => $key,
            };
            $value = self::string($data, $key, $default);
            if ($value === '' || str_starts_with($value, '/') || str_contains($value, '..')) {
                throw new InvalidArgumentException($key . ' модуля должен быть относительным путём без "..".');
            }
            $paths[$key] = $value;
        }
        $helpers = $data['helpers'] ?? null;
        if ($helpers !== null) {
            if (!is_string($helpers) || $helpers === '' || str_starts_with($helpers, '/') || str_contains($helpers, '..')) {
                throw new InvalidArgumentException('helpers модуля должен быть относительным путём без "..".');
            }
        }
        $paths['helpers'] = $helpers;

        $entrypoint = $data['entrypoint'] ?? null;
        if ($entrypoint !== null) {
            if (!is_string($entrypoint) || !preg_match('/^[A-Z][A-Za-z0-9_]*$/D', $entrypoint)) {
                throw new InvalidArgumentException('entrypoint модуля — имя класса в namespace модуля.');
            }
        }

        // Класс, принимающий изменения свойств. Какие именно свойства
        // обслуживает модуль, в манифесте не задаётся: это выбор пользователя
        // в интерфейсе модуля и хранится в `property_subscriptions`.
        $propertySubscriber = $data['property_subscriber'] ?? null;
        if ($propertySubscriber !== null) {
            if (!is_string($propertySubscriber) || !preg_match('/^[A-Z][A-Za-z0-9_]*$/D', $propertySubscriber)) {
                throw new InvalidArgumentException('property_subscriber модуля — имя класса в namespace модуля.');
            }
        }

        $storage = $data['storage'] ?? null;
        if ($storage !== null) {
            if (!is_array($storage)) {
                throw new InvalidArgumentException('storage модуля должен быть объектом.');
            }
            $driver = (string)($storage['driver'] ?? 'mysql');
            if (!in_array($driver, ['mysql', 'sqlite'], true)) {
                throw new InvalidArgumentException('storage.driver: mysql или sqlite.');
            }
            $tables = $storage['tables'] ?? [];
            if (!is_array($tables) || $tables === []) {
                throw new InvalidArgumentException('storage.tables должен содержать хотя бы одну таблицу.');
            }
            foreach ($tables as $table) {
                if (!is_string($table) || !preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $table)) {
                    throw new InvalidArgumentException('storage.tables: только [a-z][a-z0-9_].');
                }
            }
        }

        $menu = $data['menu'] ?? null;
        if ($menu !== null) {
            if (!is_array($menu) || !isset($menu['title']) || !is_string($menu['title'])) {
                throw new InvalidArgumentException('menu модуля должен содержать title.');
            }
            if (isset($menu['order']) && !is_int($menu['order'])) {
                throw new InvalidArgumentException('menu.order модуля должен быть числом.');
            }
        }

        // Настройки описываются манифестом, а значения хранит реестр модулей.
        // Модуль не создаёт свою таблицу настроек и не рисует общую форму:
        // одинаковые типы полей выглядят и проверяются в ядре одинаково.
        $settings = self::normalizeSettings($data['settings'] ?? null);

        $requires = $data['requires'] ?? [];
        if (!is_array($requires)) {
            throw new InvalidArgumentException('requires модуля должен быть объектом.');
        }
        foreach (['laiot', 'php'] as $key) {
            if (isset($requires[$key]) && !is_string($requires[$key])) {
                throw new InvalidArgumentException('requires.' . $key . ' должен быть строкой.');
            }
        }

        $dependencies = $data['dependencies'] ?? [];
        if (!is_array($dependencies)) {
            throw new InvalidArgumentException('dependencies модуля должен быть массивом.');
        }
        foreach ($dependencies as $dependency) {
            if (!is_string($dependency) || !preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $dependency)) {
                throw new InvalidArgumentException('dependencies: только id модулей.');
            }
        }

        $tasks = $data['tasks'] ?? [];
        if (!is_array($tasks)) {
            throw new InvalidArgumentException('tasks модуля должен быть объектом.');
        }
        $normalizedTasks = [];
        foreach ($tasks as $name => $task) {
            if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $name) || !is_array($task)) {
                throw new InvalidArgumentException('tasks: имя задачи и объект параметров.');
            }
            $method = $task['method'] ?? $name;
            if (!is_string($method) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $method)) {
                throw new InvalidArgumentException('tasks.' . $name . '.method: имя метода.');
            }
            $interval = $task['interval'] ?? 60;
            $timeout = $task['timeout'] ?? 60;
            if (!is_int($interval) || $interval < 1 || $interval > 86400) {
                throw new InvalidArgumentException('tasks.' . $name . '.interval: 1..86400 секунд.');
            }
            if (!is_int($timeout) || $timeout < 1 || $timeout > 3600) {
                throw new InvalidArgumentException('tasks.' . $name . '.timeout: 1..3600 секунд.');
            }
            $normalizedTasks[$name] = [
                'method' => $method,
                'interval' => $interval,
                'timeout' => $timeout,
                'enabled' => !array_key_exists('enabled', $task) || (bool)$task['enabled'],
            ];
        }

        $manifest = [
            'id' => $id,
            'type' => $type,
            'name' => self::string($data, 'name', $id),
            'version' => $version,
            'description' => self::string($data, 'description', ''),
            'namespace' => $namespace,
            'entrypoint' => $entrypoint,
            'property_subscriber' => $propertySubscriber,
            'path' => $path,
            'storage' => $storage,
            'menu' => $menu,
            'requires' => $requires,
            'dependencies' => array_values(array_unique($dependencies)),
            'permissions' => self::stringList($data, 'permissions'),
            'tasks' => $normalizedTasks,
            'settings' => $settings,
        ] + $paths;

        return new self($manifest, $path, $type);
    }

    public function id(): string
    {
        return (string)$this->data['id'];
    }

    public function isCore(): bool
    {
        return $this->type === 'core';
    }

    /**
     * Описание полей настроек из манифеста.
     *
     * @return array<string, array{title:string,type:string,default:mixed,options:array<string,string>,description:string}>
     */
    public function settingsSchema(): array
    {
        return $this->data['settings'];
    }

    public function assertCompatible(string $laiotVersion, string $phpVersion = PHP_VERSION): void
    {
        $requires = $this->data['requires'];
        foreach (['laiot' => $laiotVersion, 'php' => $phpVersion] as $key => $actual) {
            $constraint = $requires[$key] ?? null;
            if (!is_string($constraint) || $constraint === '') {
                continue;
            }
            try {
                $ok = Semver::satisfies($actual, $constraint);
            } catch (\UnexpectedValueException) {
                throw new InvalidArgumentException('Некорректное ограничение requires.' . $key . ': ' . $constraint);
            }
            if (!$ok) {
                throw new InvalidArgumentException(
                    'Модуль ' . $this->id() . ' требует ' . $key . ' ' . $constraint . ', установлено ' . $actual . '.'
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Проверяет и нормализует описание настроек модуля.
     *
     * @return array<string, array{title:string,type:string,default:mixed,options:array<string,string>,description:string}>
     */
    private static function normalizeSettings(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }
        if ($raw === []) {
            // toArray() всегда содержит пустой settings; повторная сборка
            // манифеста (installer, CLI) не должна считать это ошибкой.
            return [];
        }
        if (!is_array($raw) || array_is_list($raw)) {
            throw new InvalidArgumentException('settings модуля должен быть объектом.');
        }

        $settings = [];
        foreach ($raw as $key => $field) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $key)) {
                throw new InvalidArgumentException('settings: ключи только [a-z][a-z0-9_], 2–64 символа.');
            }
            if (!is_array($field) || array_is_list($field)) {
                throw new InvalidArgumentException('settings.' . $key . ' должен быть объектом.');
            }

            $title = $field['title'] ?? null;
            if (!is_string($title) || trim($title) === '') {
                throw new InvalidArgumentException('settings.' . $key . '.title обязателен.');
            }
            $description = $field['description'] ?? '';
            if (!is_string($description)) {
                throw new InvalidArgumentException('settings.' . $key . '.description должен быть строкой.');
            }

            $type = $field['type'] ?? 'string';
            if (!in_array($type, ['string', 'text', 'int', 'bool', 'select'], true)) {
                throw new InvalidArgumentException('settings.' . $key . '.type: string, text, int, bool или select.');
            }

            $default = $field['default'] ?? match ($type) {
                'int' => 0,
                'bool' => false,
                'select' => null,
                default => '',
            };
            $options = [];
            if ($type === 'select') {
                $rawOptions = $field['options'] ?? null;
                if (!is_array($rawOptions) || $rawOptions === [] || array_is_list($rawOptions)) {
                    throw new InvalidArgumentException('settings.' . $key . '.options должен быть непустым объектом «значение — подпись».');
                }
                foreach ($rawOptions as $value => $label) {
                    if (!is_string($value) || $value === '' || mb_strlen($value) > 64 || !is_string($label) || trim($label) === '') {
                        throw new InvalidArgumentException('settings.' . $key . '.options: строковые ключи и подписи.');
                    }
                    $options[$value] = $label;
                }
                if ($default === null) {
                    $default = array_key_first($options);
                }
                if (!is_string($default) || !array_key_exists($default, $options)) {
                    throw new InvalidArgumentException('settings.' . $key . '.default должен быть одним из options.');
                }
            } elseif ($type === 'int') {
                if (!is_int($default)) {
                    throw new InvalidArgumentException('settings.' . $key . '.default: целое число.');
                }
            } elseif ($type === 'bool') {
                if (!is_bool($default)) {
                    throw new InvalidArgumentException('settings.' . $key . '.default: true или false.');
                }
            } elseif (!is_string($default)) {
                throw new InvalidArgumentException('settings.' . $key . '.default: строка.');
            }

            $settings[$key] = [
                'title' => $title,
                'type' => $type,
                'default' => $default,
                'options' => $options,
                'description' => $description,
            ];
        }

        return $settings;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function string(array $data, string $key, string $default = ''): string
    {
        if (!isset($data[$key])) {
            return $default;
        }
        if (!is_string($data[$key])) {
            throw new InvalidArgumentException($key . ' модуля должен быть строкой.');
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    private static function stringList(array $data, string $key): array
    {
        $raw = $data[$key] ?? [];
        if (!is_array($raw)) {
            throw new InvalidArgumentException($key . ' модуля должен быть массивом.');
        }
        $list = [];
        foreach ($raw as $value) {
            if (!is_string($value) || !preg_match('/^[a-z][a-z0-9_.-]{1,63}$/D', $value)) {
                throw new InvalidArgumentException($key . ': только строковые идентификаторы.');
            }
            $list[] = $value;
        }

        return array_values(array_unique($list));
    }
}
