<?php

declare(strict_types=1);

namespace LaIOT\Services\Updates;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use LaIOT\Database\DatabaseManager;
use LaIOT\Services\Modules\ModuleManifest;
use LaIOT\Services\Modules\ModuleRegistry;
use RuntimeException;
use Throwable;

/**
 * Предварительный просмотр и запуск операции. Проверки повторяются отдельным процессом перед остановкой.
 * 2026-10-30 — полный список выбранного канала отсортирован по убыванию semver.
 * 2026-10-30 — неопределённая версия считается 0.0.0; установленный манифест имеет приоритет.
 * 2026-10-30 — общие требования проверяются в PHP приложения и CLI, pcntl требуется только CLI.
 * 2026-10-29 — номерные каналы, проверки окружения и совместимости, один снимок на исходный релиз.
 */
readonly class UpdateService
{
    /** @param array<string, mixed> $config */
    public function __construct(
        public ReleaseLayout $layout,
        public UpdateState $state,
        public ReleaseCatalog $catalog,
        public UpdateDatabase $database,
        public UpdateHost $host,
        public array $config
    ) {
    }

    /** @return array<string, mixed> */
    public function current(): array
    {
        $manifest = UpdateFiles::read($this->layout->root . '/release.json');
        if ($manifest !== []) {
            return (new ReleaseManifest($manifest))->data;
        }
        $version = trim((string)($this->config['version'] ?? ''));
        if ($version === '') {
            $version = '0.0.0';
        }
        $channel = (string)($this->config['updates']['channel'] ?? 'beta');
        return [
            'id' => $channel . '-' . $version, 'version' => $version, 'channel' => $channel,
            'commit' => str_repeat('0', 40), 'php' => '>=8.2', 'extensions' => ['pdo_mysql', 'mbstring', 'zip'],
            'modules' => $this->coreModules($this->layout->root), 'local' => true,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function refresh(): array
    {
        $releases = $this->catalog->releases();
        UpdateFiles::json($this->state->directory . '/catalog.json', ['checked_at' => gmdate('c'), 'repository' => $this->catalog->repository, 'releases' => $releases]);
        return $releases;
    }

    /** @return list<array<string, mixed>> */
    public function listing(string $channel, bool $includeCurrent = false): array
    {
        $cached = UpdateFiles::read($this->state->directory . '/catalog.json');
        $remote = ($cached['repository'] ?? '') === $this->catalog->repository ? (array)($cached['releases'] ?? []) : [];
        $releases = [];
        foreach ($remote as $release) {
            if (is_array($release)) {
                $releases[(string)$release['id']] = $release;
            }
        }
        foreach (glob($this->layout->installation . '/releases/*/release.json') ?: [] as $file) {
            $local = UpdateFiles::read($file);
            if ($local !== []) {
                $id = (new ReleaseManifest($local))->id();
                $releases[$id] = ($releases[$id] ?? ['id' => $id, 'channel' => $local['channel'], 'title' => $id, 'notes' => 'Сохранённая версия системы.']) + ['local' => true];
            }
        }
        $installed = $this->current();
        $current = $installed['id'];
        if ($includeCurrent) {
            $releases[$current] ??= $installed + ['title' => $current, 'notes' => 'Текущая установленная версия системы.'];
        }
        $releases = array_values(array_filter($releases, static fn (array $release): bool => ($release['channel'] === $channel && $release['id'] !== $current) || ($includeCurrent && $release['id'] === $current)));
        usort($releases, static function (array $left, array $right): int {
            $leftVersion = substr((string)$left['id'], 5);
            $rightVersion = substr((string)$right['id'], 5);
            return Comparator::greaterThan($leftVersion, $rightVersion) ? -1 : (Comparator::lessThan($leftVersion, $rightVersion) ? 1 : 0);
        });
        return $releases;
    }

    /** @return array<string, mixed> */
    public function release(string $id): array
    {
        ReleaseManifest::assertId($id);
        foreach ($this->listing(str_starts_with($id, 'main-') ? 'main' : 'beta') as $release) {
            if ($release['id'] === $id) {
                return $release;
            }
        }
        throw new RuntimeException('Релиз не найден. Обновите список выпусков.');
    }

    /** @param array<string, mixed> $release */
    public function manifest(array $release): ReleaseManifest
    {
        $file = $this->layout->release((string)$release['id']) . '/release.json';
        $local = is_file($file) ? new ReleaseManifest(UpdateFiles::read($file)) : null;
        if (isset($release['manifest_url'])) {
            $remote = $this->catalog->manifest($release);
            if ($local !== null && $local->data['commit'] !== $remote->data['commit']) {
                throw new RuntimeException('Этот номер уже сохранён с другим кодом. Выпустите новую версию; снимки существующей версии не заменяются чужим релизом.');
            }
            return $remote;
        }
        return $local ?? throw new RuntimeException('Сохранённый релиз недоступен.');
    }

    /** @return array<string, mixed> */
    public function preview(string $id): array
    {
        $release = $this->release($id);
        $manifest = $this->manifest($release);
        $snapshot = $this->database->info($id);
        if ($snapshot === [] && Comparator::lessThan((string)$manifest->data['version'], (string)$this->current()['version'])) {
            throw new RuntimeException('Для возврата на более старую версию нужен её сохранённый снимок БД.');
        }
        $checks = $this->checks($manifest, $snapshot);
        return ['release' => $release, 'manifest' => $manifest->data, 'snapshot' => $snapshot, 'checks' => $checks,
            'ready' => !in_array(false, array_column($checks, 'ok'), true)];
    }

    /** @param array<string, mixed> $snapshot
     * @return list<array{title:string,ok:bool,detail:string}>
     */
    public function checks(ReleaseManifest $manifest, array $snapshot = []): array
    {
        $checks = [];
        $check = static function (string $title, callable $action) use (&$checks): void {
            try {
                $checks[] = ['title' => $title, 'ok' => true, 'detail' => (string)$action()];
            } catch (Throwable $error) {
                $checks[] = ['title' => $title, 'ok' => false, 'detail' => $error->getMessage()];
            }
        };
        $check('Платформа и пользователь', fn (): string => $this->runtimeIdentity());
        $check('PHP и расширения', function () use ($manifest): string {
            $this->assertPhpPlatform($manifest, PHP_VERSION, get_loaded_extensions(), false);
            $cli = $this->host->platform();
            $this->assertPhpPlatform($manifest, $cli['version'], $cli['extensions'], true);
            return 'PHP приложения ' . PHP_VERSION . '; PHP CLI ' . $cli['version'] . '; требования релиза выполнены.';
        });
        $check('Файлы и место на диске', function (): string {
            foreach ([$this->layout->installation, $this->layout->storage] as $directory) {
                if (!is_writable($directory)) {
                    throw new RuntimeException('Пользователь сайта не может писать в каталог установки или var.');
                }
                $free = disk_free_space($directory);
                if ($free === false || $free < 512 * 1024 * 1024) {
                    throw new RuntimeException('Требуется минимум 512 МБ свободного места; большой дамп потребует больше.');
                }
            }
            return 'Каталоги доступны; есть место для подготовки релиза.';
        });
        $check('База данных и резервная копия', fn (): string => $this->database->check());
        $check('Фоновые службы', function (): string {
            $this->host->services();
            return 'Агент отвечает; обновление выполняет отдельная служба.';
        });
        $check('Совместимость модулей', function () use ($manifest, $snapshot): string {
            $inventory = $this->inventory();
            $definitions = [];
            foreach ($inventory as $id => $module) {
                if ($module['type'] === 'core') {
                    continue;
                }
                $definition = ModuleManifest::fromFile($this->layout->modules . '/' . $id . '/module.json', 'modules/' . $id, 'module');
                $definition->assertCompatible((string)$manifest->data['version']);
                $definitions[$id] = $definition;
                if ($snapshot !== [] && (($snapshot['modules'][$id]['version'] ?? null) !== $module['version'])) {
                    throw new RuntimeException('Версия модуля ' . $id . ' отличается от снимка БД.');
                }
            }
            foreach ((array)($snapshot['modules'] ?? []) as $id => $module) {
                if (($module['type'] ?? '') !== 'core' && !isset($inventory[$id])) {
                    throw new RuntimeException('Для снимка БД отсутствует модуль ' . $id . '.');
                }
            }
            foreach ($manifest->data['modules'] as $id => $module) {
                $definition = ModuleManifest::fromArray($module, '', 'core');
                $definition->assertCompatible((string)$manifest->data['version']);
                $definitions[$id] = $definition;
            }
            $enabled = $snapshot === [] ? $inventory : (array)$snapshot['modules'];
            foreach ($definitions as $id => $definition) {
                if (($enabled[$id]['enabled'] ?? $definition->isCore()) !== true) {
                    continue;
                }
                foreach ($definition->data['dependencies'] as $dependency) {
                    if (!isset($definitions[$dependency]) || ($enabled[$dependency]['enabled'] ?? $definitions[$dependency]->isCore()) !== true) {
                        throw new RuntimeException('Модулю ' . $id . ' требуется включённый модуль ' . $dependency . '.');
                    }
                }
            }
            return 'Установленные модули совместимы; сторонний код сохранится.';
        });
        return $checks;
    }

    /** @param list<string> $extensions */
    private function assertPhpPlatform(ReleaseManifest $manifest, string $version, array $extensions, bool $cli): void
    {
        $context = $cli ? 'PHP CLI' : 'PHP приложения';
        foreach (array_merge([$manifest->data['php']], (array)($manifest->data['php_requirements'] ?? [])) as $requirement) {
            if (!is_string($requirement) || !Semver::satisfies($version, $requirement)) {
                throw new RuntimeException($context . ' ' . $version . ' не соответствует требованию релиза: ' . (string)$requirement);
            }
        }
        $extensions = array_map('strtolower', $extensions);
        foreach ($manifest->data['extensions'] as $extension) {
            // В Ubuntu pcntl встроен только в CLI; его отсутствие в FPM не мешает сайту.
            if (!$cli && $extension === 'pcntl') {
                continue;
            }
            if (!is_string($extension) || !in_array(strtolower($extension), $extensions, true)) {
                throw new RuntimeException('Необходимо расширение ' . $context . ' ' . (string)$extension);
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function inventory(): array
    {
        $database = new DatabaseManager((array)$this->config['database']);
        $registry = new ModuleRegistry($database);
        $modules = [];
        foreach ($registry->all() as $row) {
            $id = (string)$row['module_id'];
            if (preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $id) !== 1) {
                throw new RuntimeException('В реестре есть некорректный id модуля.');
            }
            $version = (string)$row['version'];
            if ($row['type'] !== 'core') {
                $definition = ModuleManifest::fromFile($this->layout->modules . '/' . $id . '/module.json', 'modules/' . $id, 'module');
                if ($definition->id() !== $id) {
                    throw new RuntimeException('Манифест модуля не соответствует его каталогу.');
                }
                $version = (string)$definition->data['version'];
            }
            $modules[$id] = ['version' => $version, 'type' => (string)$row['type'], 'enabled' => (bool)$row['enabled']];
        }
        foreach ($this->coreModules($this->layout->root) as $id => $module) {
            $modules[$id] = ['version' => $module['version'], 'type' => 'core', 'enabled' => $modules[$id]['enabled'] ?? true];
        }
        ksort($modules);
        return $modules;
    }

    public function queue(string $id, bool $confirmed): string
    {
        $lock = $this->state->lock('request');
        try {
            if ($this->state->busy()) {
                throw new RuntimeException('Сначала завершите или восстановите предыдущую операцию.');
            }
            $preview = $this->preview($id);
            if (!$preview['ready']) {
                throw new RuntimeException('Исправьте ошибки предварительной проверки.');
            }
            if (!$confirmed) {
                throw new RuntimeException('Подтвердите остановку системы и возможное восстановление данных.');
            }
            $key = bin2hex(random_bytes(32));
            UpdateFiles::write($this->state->directory . '/recovery.key', $key);
            UpdateFiles::json($this->state->directory . '/operation.json', [
                'id' => bin2hex(random_bytes(16)), 'status' => 'queued', 'heartbeat' => time(),
                'source' => $this->current(), 'target' => $preview['manifest'], 'release' => $preview['release'],
                'restore_target' => $preview['snapshot'] !== [], 'snapshot_ready' => false, 'database_changed' => false,
                'maintenance' => false, 'services' => [], 'events' => [], 'action' => 'install',
                'access' => hash('sha256', $key), 'created_at' => gmdate('c'),
                'message' => 'Ожидаем запуска службы обновления.',
            ]);
            try {
                $this->host->launch();
            } catch (Throwable $error) {
                $this->state->save(['status' => 'failed', 'message' => 'Служба не запустилась. Проверьте установку laiot-update.service.']);
                throw $error;
            }
            return $key;
        } finally {
            fclose($lock);
        }
    }

    public function runtimeIdentity(): string
    {
        if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid')) {
            throw new RuntimeException('Обновление выполняется на Linux под пользователем сайта.');
        }
        if (posix_geteuid() === 0 || fileowner($this->layout->storage) !== posix_geteuid()
            || fileowner($this->layout->environment) !== posix_geteuid()) {
            throw new RuntimeException('PHP, .env и var должны принадлежать одному пользователю сайта; запуск от root запрещён.');
        }
        if ((string)($this->config['encryption']['key'] ?? '') === '') {
            throw new RuntimeException('Отсутствует ключ шифрования APP_KEY.');
        }
        return 'Linux; пользователь PHP владеет .env и хранилищем.';
    }

    /** Проверка не обращается к частично восстановленной БД.
     * @param array<string, mixed> $snapshot
     */
    public function recoveryModules(ReleaseManifest $source, array $snapshot): void
    {
        foreach ((array)($snapshot['modules'] ?? []) as $id => $module) {
            if (($module['type'] ?? '') === 'core') {
                continue;
            }
            if (preg_match('/^[a-z][a-z0-9_]{1,63}$/D', (string)$id) !== 1) {
                throw new RuntimeException('Некорректный модуль в снимке.');
            }
            $definition = ModuleManifest::fromFile($this->layout->modules . '/' . $id . '/module.json', 'modules/' . $id, 'module');
            $definition->assertCompatible((string)$source->data['version']);
            if ($definition->data['version'] !== $module['version']) {
                throw new RuntimeException('Код модуля ' . $id . ' изменился после снимка. Верните его совместимую версию перед восстановлением.');
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function coreModules(string $root): array
    {
        $modules = [];
        foreach (glob($root . '/modules/core/*/module.json') ?: [] as $file) {
            $manifest = ModuleManifest::fromFile($file, 'modules/core/' . basename(dirname($file)), 'core');
            $modules[$manifest->id()] = $manifest->data;
        }
        return $modules;
    }
}
