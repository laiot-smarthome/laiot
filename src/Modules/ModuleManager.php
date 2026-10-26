<?php

declare(strict_types=1);

namespace MajorDoMo\Modules;

use Illuminate\Container\Container;
use Illuminate\Routing\Router;
use Illuminate\View\Factory;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Support\HelperLoader;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Менеджер модулей MyDoMo SDK.
 *
 * Core-модули лежат в `modules/core/<id>` и всегда включены. Сторонние —
 * в `modules/<id>` и подключаются только после `module:install` и при
 * enabled=1 в `module_registry`. Все манифесты валидируются ModuleManifest.
 */
final class ModuleManager
{
    /** @var array<string, array<string, mixed>> */
    private array $modules = [];

    /** @var array<string, array<string, mixed>> */
    private array $discovered = [];

    private readonly ModuleRegistry $registry;

    public function __construct(
        private readonly string $root,
        private readonly DatabaseManager $database,
        private readonly Container $container,
        private readonly HelperLoader $helpers,
        ?ModuleRegistry $registry = null
    ) {
        $this->registry = $registry ?? new ModuleRegistry($database);
        $this->discover();
        $this->bootEntries();
    }

    /**
     * Подключённые (core + enabled third-party) модули.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * Все найденные манифесты, включая доступные, но не установленные.
     *
     * @return array<string, array<string, mixed>>
     */
    public function discovered(): array
    {
        return $this->discovered;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array
    {
        return $this->modules[$id] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function manifest(string $id): ?array
    {
        return $this->discovered[$id]['manifest'] ?? null;
    }

    public function db(string $id): ?ModuleDb
    {
        return $this->discovered[$id]['db'] ?? null;
    }

    /**
     * Публичный сервис модуля для helper-функций и других модулей.
     *
     * Класс обязан принадлежать namespace модуля: нельзя через helper
     * заставить ядро создать произвольный сервис.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function service(string $id, string $class): object
    {
        $module = $this->modules[$id] ?? $this->discovered[$id] ?? null;
        if ($module === null) {
            throw new RuntimeException('Модуль не найден: ' . $id);
        }
        $namespace = (string)$module['namespace'];
        if (!str_starts_with($class, $namespace)) {
            throw new RuntimeException('Сервис не принадлежит модулю ' . $id);
        }

        $key = 'module.service.' . $id . '.' . $class;
        if ($this->container->bound($key)) {
            /** @var T $service */
            $service = $this->container->make($key);

            return $service;
        }

        /** @var T $service */
        $service = $this->container->make($class);
        $this->container->instance($key, $service);

        return $service;
    }

    /**
     * @return list<array{id:string,label:string,href:string,icon:string}>
     */
    public function menuItems(): array
    {
        $items = [];
        foreach ($this->modules as $id => $module) {
            $menu = is_array($module['menu'] ?? null) ? $module['menu'] : [];
            $items[] = [
                'id' => (string)$id,
                'label' => (string)($menu['title'] ?? $module['name'] ?? $id),
                'href' => '/module/' . $id,
                'icon' => (string)($menu['icon'] ?? 'ti-puzzle'),
                'order' => (int)($menu['order'] ?? 100),
            ];
        }
        usort($items, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return array_map(static function (array $item): array {
            unset($item['order']);

            return $item;
        }, $items);
    }

    public function register(Router $router, Factory $view): void
    {
        foreach ($this->modules as $id => $module) {
            $views = $module['path'] . '/' . $module['views'];
            if (is_dir($views)) {
                $view->addNamespace($id, $views);
            }

            $routesFile = $module['path'] . '/' . $module['routes'];
            if (!is_file($routesFile)) {
                throw new RuntimeException('Маршруты модуля не найдены: ' . $id);
            }

            $routes = require $routesFile;
            if (!is_callable($routes)) {
                throw new RuntimeException('routes.php модуля должен возвращать callable: ' . $id);
            }

            $moduleDb = $module['db'] ?? null;
            $moduleBus = $module['bus'];
            $bindServices = function (mixed $request, callable $next) use ($moduleDb, $moduleBus): Response {
                if ($moduleDb instanceof ModuleDb) {
                    $this->container->instance(ModuleDb::class, $moduleDb);
                }
                $this->container->instance(ModuleBus::class, $moduleBus);

                return $next($request);
            };

            $router->group([
                'prefix' => '/module/' . $id,
                'as' => $id . '.',
                'middleware' => ['request-id', 'auth', $bindServices],
            ], $routes);
        }
    }

    /**
     * @return list<array{module:string,migration:string}>
     */
    public function runMigrations(string $id): array
    {
        $module = $this->discovered[$id] ?? null;
        if ($module === null) {
            throw new RuntimeException('Модуль не найден: ' . $id);
        }
        $directory = $module['path'] . '/' . $module['migrations'];
        if (!is_dir($directory)) {
            return [];
        }
        $files = glob($directory . '/*.php') ?: [];
        sort($files);
        $applied = $this->registry->appliedMigrations($id);
        $executed = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }
            $migration = require $file;
            if (!is_callable($migration)) {
                throw new RuntimeException('Миграция модуля должна возвращать callable: ' . $name);
            }
            $migration($this->database->connection());
            $this->registry->markMigration($id, $name);
            $executed[] = ['module' => $id, 'migration' => $name];
        }

        return $executed;
    }

    public function context(string $id): ModuleContext
    {
        $module = $this->discovered[$id] ?? null;
        if ($module === null) {
            throw new RuntimeException('Модуль не найден: ' . $id);
        }

        return new ModuleContext(
            $module['manifest'],
            $module['bus'],
            $this->container,
            $this->root,
            $module['db'] ?? null
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function registryRow(string $id): ?array
    {
        try {
            return $this->registry->find($id);
        } catch (Throwable) {
            return null;
        }
    }

    public function registry(): ModuleRegistry
    {
        return $this->registry;
    }

    private function discover(): void
    {
        $roots = [
            ['path' => $this->root . '/modules/core', 'type' => 'core'],
            ['path' => $this->root . '/modules', 'type' => 'module'],
        ];

        $registry = [];
        try {
            foreach ($this->registry->all() as $row) {
                $registry[(string)$row['module_id']] = $row;
            }
        } catch (Throwable $error) {
            error_log('MyDoMo module registry is not available: ' . $error->getMessage());
        }

        foreach ($roots as $root) {
            $base = realpath($root['path']);
            if ($base === false) {
                continue;
            }
            foreach (glob($base . '/*/module.json') ?: [] as $manifestFile) {
                $path = realpath(dirname($manifestFile));
                if ($path === false || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
                    continue;
                }
                if ($root['type'] === 'module' && $path === $base . DIRECTORY_SEPARATOR . 'core') {
                    continue;
                }
                try {
                    $manifest = ModuleManifest::fromFile($manifestFile, $path, $root['type']);
                } catch (Throwable $error) {
                    error_log('MyDoMo module manifest is invalid: ' . $error->getMessage());
                    continue;
                }
                if ($manifest->id() !== basename($path)) {
                    error_log('MyDoMo module id does not match directory: ' . $manifest->id());
                    continue;
                }

                $module = $manifest->toArray();
                $module['db'] = is_array($module['storage'])
                    ? new ModuleDb($manifest->id(), $module['storage'], $this->database, $path)
                    : null;
                $module['bus'] = new ModuleBus($manifest->id(), $this->database);
                $module['manifest'] = $manifest->toArray();
                $module['core'] = $manifest->isCore();
                $module['installed'] = $manifest->isCore() || isset($registry[$manifest->id()]);
                $module['enabled'] = $manifest->isCore()
                    || (isset($registry[$manifest->id()]) && (int)$registry[$manifest->id()]['enabled'] === 1);

                $this->registerAutoloader($module);
                $this->discovered[$manifest->id()] = $module;
                if ($module['installed'] && $module['enabled']) {
                    $this->modules[$manifest->id()] = $module;
                    $this->helpers->loadModule(
                        $manifest->id(),
                        $path,
                        $module['helpers'] ?? null
                    );
                }
            }
        }

        ksort($this->modules);
        ksort($this->discovered);
        foreach ($this->modules as $id => $module) {
            if (!$this->dependencyMissing($module)) {
                continue;
            }
            error_log('MyDoMo module dependencies are missing: ' . $id);
            unset($this->modules[$id]);
        }
    }

    private function bootEntries(): void
    {
        foreach ($this->modules as $id => $module) {
            $entrypoint = $module['entrypoint'] ?? null;
            if (!is_string($entrypoint) || $entrypoint === '') {
                continue;
            }
            if ($this->dependencyMissing($module)) {
                continue;
            }
            $class = (string)$module['namespace'] . $entrypoint;
            try {
                $instance = $this->container->make($class);
                if ($instance instanceof Module) {
                    $instance->boot($this->context($id));
                }
            } catch (Throwable $error) {
                error_log('MyDoMo module boot failed for ' . $id . ': ' . $error->getMessage());
            }
        }
    }

    /**
     * @param array<string, mixed> $module
     */
    private function dependencyMissing(array $module): bool
    {
        foreach ((array)($module['dependencies'] ?? []) as $dependency) {
            if (!is_string($dependency) || !isset($this->modules[$dependency])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $module
     */
    private function registerAutoloader(array $module): void
    {
        $prefix = (string)$module['namespace'];
        $src = (string)$module['path'] . '/' . (string)$module['src'];

        spl_autoload_register(static function (string $class) use ($prefix, $src): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = $src . '/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
