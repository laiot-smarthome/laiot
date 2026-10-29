<?php

declare(strict_types=1);

namespace LaIOT\Services\Modules;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use LaIOT\Services\Files\FileStorage;
use Throwable;

/**
 * Lifecycle сторонних модулей: install / enable / disable / remove.
 *
 * Core-модули неизменяемы. Миграции модуля лежат в его `migrations/` и
 * выполняются один раз; entrypoint (если есть) получает ModuleContext.
 *
 * При установке и включении регистрируются разрешения из манифеста и
 * публикуются ассеты; при удалении они убираются. `--purge` дополнительно
 * удаляет объявленные таблицы модуля и его SQLite-данные.
 *
 * 2026-10-28 — файлы var/files/modules/<id> удаляются и без --purge.
 * 2026-10-27 — permissions/assets из манифеста и purge данных при удалении.
 * 2026-10-27 — в списке видны настройки и подписчик модуля.
 * 2026-10-27 — core: enable/disable разрешены, install/remove запрещены.
 */
final readonly class ModuleInstaller
{
    public function __construct(
        private ModuleManager $modules,
        private string $laiotVersion,
        private ModulePermissions $permissions,
        private ModuleAssets $assets,
        private FileStorage $files
    ) {
    }

    /**
     * @return array{id:string,migrations:list<array{module:string,migration:string}>}
     */
    public function install(string $id): array
    {
        $module = $this->requireAvailable($id);
        $manifest = $this->manifest($module);
        if ($manifest->isCore()) {
            throw new InvalidArgumentException('Core-модуль нельзя установить или включить вручную.');
        }
        if ($this->modules->registryRow($id) !== null) {
            throw new InvalidArgumentException('Модуль уже установлен. Используйте module:enable.');
        }
        $this->assertCompatible($manifest);
        $this->assertDependencies($manifest);

        $registry = $this->registry();
        $registry->install($manifest, false);
        try {
            $migrations = $this->modules->runMigrations($id);
            $this->entrypoint($id, 'install');
            $this->sync($id, $manifest, $module);
            $registry->setEnabled($id, true);
            $this->entrypoint($id, 'enabled');

            return ['id' => $id, 'migrations' => $migrations];
        } catch (Throwable $error) {
            $this->permissions->forget($id, (array)$manifest->data['permissions']);
            $this->assets->remove($id);
            $registry->remove($id);
            throw $error;
        }
    }

    public function enable(string $id): void
    {
        $module = $this->requireAvailable($id);
        $manifest = $this->manifest($module);
        $registry = $this->registry();
        $row = $registry->find($id);
        if ((bool)($module['core'] ?? false)) {
            // Core-модуль поставляется включённым: строки реестра может не
            // быть. Enable возвращает его после выключения и не трогает
            // миграции/ассеты — они обновляются вместе с приложением.
            $this->assertCompatible($manifest);
            $this->assertDependencies($manifest);
            if ($row !== null) {
                $registry->ensure($manifest, true);
                $this->entrypoint($id, 'enabled');
            }

            return;
        }
        if ($row === null) {
            throw new InvalidArgumentException('Модуль не установлен.');
        }
        $this->assertCompatible($manifest);
        $this->assertDependencies($manifest);
        $this->sync($id, $manifest, $module);
        $registry->setEnabled($id, true);
        $this->entrypoint($id, 'enabled');
    }

    public function disable(string $id): void
    {
        $module = $this->requireAvailable($id);
        $manifest = $this->manifest($module);
        $registry = $this->registry();
        if ((bool)($module['core'] ?? false)) {
            // Строка реестра появляется при первом выключении: до этого
            // core-модуль считается включённым по поставке.
            $this->entrypoint($id, 'disabled');
            $registry->ensure($manifest, false);

            return;
        }
        if ($registry->find($id) === null) {
            throw new InvalidArgumentException('Модуль не установлен.');
        }
        $this->entrypoint($id, 'disabled');
        $registry->setEnabled($id, false);
    }

    public function remove(string $id, bool $purge = false): void
    {
        $module = $this->requireAvailable($id);
        if ((bool)($module['core'] ?? false)) {
            throw new InvalidArgumentException('Core-модуль нельзя удалить.');
        }
        $registry = $this->registry();
        if ($registry->find($id) === null) {
            throw new InvalidArgumentException('Модуль не установлен.');
        }
        $manifest = $this->manifest($module);
        $this->entrypoint($id, 'uninstall');
        $this->files->removeModule($id);
        $registry->remove($id);
        $this->permissions->forget($id, (array)$manifest->data['permissions']);
        $this->assets->remove($id);
        if ($purge) {
            $this->purgeData($module);
        }
    }

    /**
     * Удаляет только данные модуля: объявленные таблицы и SQLite-файл.
     * Код модуля остаётся — его версия живёт в пакете/каталоге modules/.
     *
     * @param array<string, mixed> $module
     */
    private function purgeData(array $module): void
    {
        $id = (string)$module['id'];
        $storage = is_array($module['storage'] ?? null) ? $module['storage'] : [];
        $tables = is_array($storage['tables'] ?? null) ? $storage['tables'] : [];
        $db = $module['db'] ?? null;

        if ($db instanceof ModuleDb) {
            foreach ($tables as $table) {
                if (is_string($table) && $table !== '') {
                    try {
                        $db->dropTable($table);
                    } catch (Throwable) {
                        // Таблица могла быть не создана; остальные всё равно чистим.
                    }
                }
            }
        }

        if (($storage['driver'] ?? 'mysql') === 'sqlite') {
            $directory = (string)$module['path'] . '/data';
            if (is_dir($directory)) {
                (new Filesystem())->deleteDirectory($directory);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $rows = [];
        foreach ($this->modules->discovered() as $id => $module) {
            $row = $this->modules->registryRow($id);
            $manifest = is_array($module['manifest'] ?? null) ? $module['manifest'] : [];
            $storage = is_array($manifest['storage'] ?? null) ? $manifest['storage'] : [];
            $tasks = is_array($manifest['tasks'] ?? null) ? $manifest['tasks'] : [];
            $rows[] = [
                'id' => $id,
                'name' => (string)($module['name'] ?? $id),
                'type' => (string)($module['type'] ?? 'module'),
                'version' => (string)($module['version'] ?? '0.0.0'),
                'description' => (string)($manifest['description'] ?? ''),
                'tables' => array_values(array_filter(
                    is_array($storage['tables'] ?? null) ? $storage['tables'] : [],
                    static fn (mixed $table): bool => is_string($table) && $table !== ''
                )),
                'tasks' => array_keys($tasks),
                'settings' => array_keys(is_array($manifest['settings'] ?? null) ? $manifest['settings'] : []),
                'subscriber' => is_string($manifest['property_subscriber'] ?? null) && $manifest['property_subscriber'] !== '',
                'core' => (bool)($module['core'] ?? false),
                'installed' => (bool)($module['core'] ?? false) || $row !== null,
                'enabled' => (bool)($module['enabled'] ?? false),
                'path' => (string)($module['path'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $module
     */
    private function manifest(array $module): ModuleManifest
    {
        return ModuleManifest::fromArray($module['manifest'], (string)$module['path'], (string)($module['type'] ?? 'module'));
    }

    /**
     * @return array<string, mixed>
     */
    private function requireAvailable(string $id): array
    {
        $module = $this->modules->discovered()[$id] ?? null;
        if ($module === null) {
            throw new InvalidArgumentException('Модуль не найден в modules/: ' . $id);
        }

        return $module;
    }

    private function registry(): ModuleRegistry
    {
        $registry = $this->modules->registry();
        if (!$registry->available()) {
            throw new InvalidArgumentException('Таблица module_registry не создана. Выполните php bin/laiot db:migrate.');
        }

        return $registry;
    }

    private function assertCompatible(ModuleManifest $manifest): void
    {
        $manifest->assertCompatible($this->laiotVersion, PHP_VERSION);
    }

    /**
     * Регистрирует разрешения и публикует ассеты модуля.
     *
     * @param array<string, mixed> $module
     */
    private function sync(string $id, ModuleManifest $manifest, array $module): void
    {
        $this->permissions->register($id, (array)$manifest->data['permissions']);
        $this->assets->publish(
            $id,
            (string)$module['path'],
            (string)($manifest->data['assets'] ?? 'assets')
        );
    }

    private function assertDependencies(ModuleManifest $manifest): void
    {
        foreach ((array)$manifest->data['dependencies'] as $dependency) {
            if (!is_string($dependency)) {
                continue;
            }
            if ($this->modules->get($dependency) === null) {
                throw new InvalidArgumentException('Не включена зависимость: ' . $dependency);
            }
        }
    }

    private function entrypoint(string $id, string $method): void
    {
        $module = $this->modules->discovered()[$id] ?? null;
        if ($module === null) {
            return;
        }
        $entrypoint = $module['entrypoint'] ?? null;
        if (!is_string($entrypoint) || $entrypoint === '') {
            return;
        }
        $class = (string)$module['namespace'] . $entrypoint;
        if (!class_exists($class)) {
            throw new InvalidArgumentException('Entrypoint модуля не найден: ' . $class);
        }
        $instance = $this->modules->context($id)->container()->make($class);
        if ($instance instanceof Module) {
            $instance->{$method}($this->modules->context($id));
        }
    }
}
