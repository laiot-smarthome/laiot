<?php

declare(strict_types=1);

namespace MajorDoMo\Modules;

use Illuminate\Routing\Router;
use Illuminate\View\Factory;
use RuntimeException;

/**
 * Обнаружение и подключение модулей MyDoMo.
 *
 * Пока модуль — это каталог с module.json, необязательным autoload-namespace
 * и routes.php. Реестр установленных модулей в БД появится отдельным этапом;
 * сейчас discover() нужен, чтобы проверить контракт на живом модуле.
 */
final class ModuleManager
{
    /** @var array<string, array<string, mixed>> */
    private array $modules = [];

    public function __construct(private readonly string $root)
    {
        $this->discover();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->modules;
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

            $router->group([
                'prefix' => '/module/' . $id,
                'as' => $id . '.',
                'middleware' => ['request-id', 'auth'],
            ], $routes);
        }
    }

    private function discover(): void
    {
        $modulesRoot = realpath($this->root . '/modules');
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

            $this->registerAutoloader($module);
            $this->modules[$module['id']] = $module;
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
            'name' => (string)($manifest['name'] ?? $id),
            'version' => (string)($manifest['version'] ?? '0.0.0'),
            'path' => $path,
            'namespace' => $namespace,
            'src' => $src,
            'routes' => $routes,
            'views' => $views,
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
