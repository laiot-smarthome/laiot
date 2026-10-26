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
 * Обнаружение и подключение core-модулей MyDoMo.
 *
 * Модуль — каталог `modules/core/<id>` с module.json, autoload-namespace
 * и routes.php. Core-модули нельзя удалить/отключить; их маршруты
 * регистрируются с auth и получают ModuleDb/ModuleBus своего модуля.
 */
final class ModuleManager
{
    /** @var array<string, array<string, mixed>> */
    private array $modules = [];

    public function __construct(
        private readonly string $root,
        private readonly DatabaseManager $database,
        private readonly Container $container,
        private readonly HelperLoader $helpers
    ) {
        $this->discover();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array
    {
        return $this->modules[$id] ?? null;
    }

    public function db(string $id): ?ModuleDb
    {
        $module = $this->modules[$id] ?? null;

        return $module['db'] ?? null;
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
        $module = $this->modules[$id] ?? null;
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
                'icon' => 'ti-puzzle',
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

    private function discover(): void
    {
        $modulesRoot = realpath($this->root . '/modules/core');
        if ($modulesRoot === false) {
            return;
        }

        foreach (glob($modulesRoot . '/*/module.json') ?: [] as $manifestFile) {
            $path = realpath(dirname($manifestFile));
            if ($path === false || !str_starts_with($path, $modulesRoot . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $module = $this->readManifest($manifestFile, $path);
            if ($module === null) {
                continue;
            }

            try {
                $module['db'] = is_array($module['storage'])
                    ? new ModuleDb(
                        (string)$module['id'],
                        $module['storage'],
                        $this->database,
                        (string)$module['path']
                    )
                    : null;
                $module['bus'] = new ModuleBus((string)$module['id'], $this->database);
            } catch (Throwable $error) {
                error_log('MyDoMo module storage is invalid for ' . $module['id'] . ': ' . $error->getMessage());
                continue;
            }

            $this->registerAutoloader($module);
            $this->modules[$module['id']] = $module;
            $this->helpers->loadModule(
                (string)$module['id'],
                (string)$module['path'],
                $module['helpers'] ?? null
            );
        }

        ksort($this->modules);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readManifest(string $file, string $path): ?array
    {
        $json = file_get_contents($file);
        if ($json === false) {
            return null;
        }

        $manifest = json_decode($json, true);
        if (!is_array($manifest)) {
            error_log('MyDoMo module manifest is not valid JSON: ' . $file);

            return null;
        }

        $id = (string)($manifest['id'] ?? '');
        $namespace = (string)($manifest['namespace'] ?? '');
        $src = (string)($manifest['src'] ?? 'src');
        $routes = (string)($manifest['routes'] ?? 'routes.php');
        $views = (string)($manifest['views'] ?? 'views');

        if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $id)
            || !preg_match('/^[A-Za-z][A-Za-z0-9_\\\\]*\\\\$/D', $namespace)
            || str_contains($src, '..') || str_contains($routes, '..') || str_contains($views, '..')) {
            error_log('MyDoMo module manifest is invalid: ' . $file);

            return null;
        }

        return [
            'id' => $id,
            'type' => (string)($manifest['type'] ?? 'core'),
            'name' => (string)($manifest['name'] ?? $id),
            'version' => (string)($manifest['version'] ?? '0.0.0'),
            'path' => $path,
            'namespace' => $namespace,
            'src' => $src,
            'routes' => $routes,
            'views' => $views,
            'storage' => is_array($manifest['storage'] ?? null) ? $manifest['storage'] : null,
            'menu' => is_array($manifest['menu'] ?? null) ? $manifest['menu'] : null,
            'helpers' => $manifest['helpers'] ?? null,
        ];
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
