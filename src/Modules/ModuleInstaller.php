<?php

declare(strict_types=1);

namespace MajorDoMo\Modules;

use InvalidArgumentException;
use Throwable;

/**
 * Lifecycle сторонних модулей: install / enable / disable / remove.
 *
 * Core-модули неизменяемы. Миграции модуля лежат в его `migrations/` и
 * выполняются один раз; entrypoint (если есть) получает ModuleContext.
 */
final readonly class ModuleInstaller
{
    public function __construct(
        private ModuleManager $modules,
        private string $mydomoVersion
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
            $registry->setEnabled($id, true);
            $this->entrypoint($id, 'enabled');

            return ['id' => $id, 'migrations' => $migrations];
        } catch (Throwable $error) {
            $registry->remove($id);
            throw $error;
        }
    }

    public function enable(string $id): void
    {
        $module = $this->requireAvailable($id);
        if ((string)$module['type'] === 'core' || (bool)($module['core'] ?? false)) {
            throw new InvalidArgumentException('Core-модуль всегда включён.');
        }
        $manifest = $this->manifest($module);
        $row = $this->registry();
        if ($row->find($id) === null) {
            throw new InvalidArgumentException('Модуль не установлен.');
        }
        $this->assertCompatible($manifest);
        $this->assertDependencies($manifest);
        $row->setEnabled($id, true);
        $this->entrypoint($id, 'enabled');
    }

    public function disable(string $id): void
    {
        $module = $this->requireAvailable($id);
        if ((bool)($module['core'] ?? false)) {
            throw new InvalidArgumentException('Core-модуль нельзя выключить.');
        }
        $registry = $this->registry();
        if ($registry->find($id) === null) {
            throw new InvalidArgumentException('Модуль не установлен.');
        }
        $this->entrypoint($id, 'disabled');
        $registry->setEnabled($id, false);
    }

    public function remove(string $id): void
    {
        $module = $this->requireAvailable($id);
        if ((bool)($module['core'] ?? false)) {
            throw new InvalidArgumentException('Core-модуль нельзя удалить.');
        }
        $registry = $this->registry();
        if ($registry->find($id) === null) {
            throw new InvalidArgumentException('Модуль не установлен.');
        }
        $this->entrypoint($id, 'uninstall');
        $registry->remove($id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $rows = [];
        foreach ($this->modules->discovered() as $id => $module) {
            $row = $this->modules->registryRow($id);
            $rows[] = [
                'id' => $id,
                'name' => (string)($module['name'] ?? $id),
                'type' => (string)($module['type'] ?? 'module'),
                'version' => (string)($module['version'] ?? '0.0.0'),
                'core' => (bool)($module['core'] ?? false),
                'installed' => (bool)($module['core'] ?? false) || $row !== null,
                'enabled' => (bool)($module['core'] ?? false) || ($row !== null && (int)$row['enabled'] === 1),
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
        return ModuleManifest::fromArray($module['manifest'], (string)$module['path'], 'module');
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
            throw new InvalidArgumentException('Таблица module_registry не создана. Выполните php bin/mydomo db:migrate.');
        }

        return $registry;
    }

    private function assertCompatible(ModuleManifest $manifest): void
    {
        $manifest->assertCompatible($this->mydomoVersion, PHP_VERSION);
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
