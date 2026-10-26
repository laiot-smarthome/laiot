<?php

declare(strict_types=1);

namespace MajorDoMo\Modules;

use Composer\Semver\Semver;
use InvalidArgumentException;
use JsonException;

/**
 * Манифест модуля MyDoMo SDK.
 *
 * `module.json` — единственный контракт модуля: id, namespace, точки входа,
 * маршруты, представления, миграции, storage, menu, requires/dependencies.
 * Никаких legacy-полей `project_modules` здесь нет.
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

        $requires = $data['requires'] ?? [];
        if (!is_array($requires)) {
            throw new InvalidArgumentException('requires модуля должен быть объектом.');
        }
        foreach (['mydomo', 'php'] as $key) {
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

        $manifest = [
            'id' => $id,
            'type' => $type,
            'name' => self::string($data, 'name', $id),
            'version' => $version,
            'description' => self::string($data, 'description', ''),
            'namespace' => $namespace,
            'entrypoint' => $entrypoint,
            'path' => $path,
            'storage' => $storage,
            'menu' => $menu,
            'requires' => $requires,
            'dependencies' => array_values(array_unique($dependencies)),
            'permissions' => self::stringList($data, 'permissions'),
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

    public function assertCompatible(string $mydomoVersion, string $phpVersion = PHP_VERSION): void
    {
        $requires = $this->data['requires'];
        foreach (['mydomo' => $mydomoVersion, 'php' => $phpVersion] as $key => $actual) {
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
