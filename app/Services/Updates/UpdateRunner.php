<?php

declare(strict_types=1);

namespace LaIOT\Services\Updates;

use Composer\Semver\Comparator;
use RuntimeException;
use Throwable;

/**
 * Фазы записываются ДО изменения БД или ссылки current; после сбоя решение принимает пользователь.
 * 2026-10-29 — отдельный процесс, остановка записи, снимок, миграции, проверка и восстановление.
 */
final readonly class UpdateRunner
{
    public function __construct(private UpdateService $updates)
    {
    }

    public function run(): void
    {
        $this->updates->runtimeIdentity();
        $state = $this->updates->state;
        $lock = $state->lock('run');
        if (($state->raw()['status'] ?? '') !== 'queued') {
            fclose($lock);
            throw new RuntimeException('Нет подтверждённой операции. Откройте страницу восстановления.');
        }
        $httpLock = null;
        try {
            $operation = $state->raw();
            $state->save(['status' => 'running', 'error' => '']);
            $action = (string)($operation['action'] ?? 'install');
            if ($action !== 'install') {
                $httpLock = $this->quiesce();
                $operation = $state->raw();
                if (isset($operation['completion']) && $action === 'retry') {
                    $completion = $operation['completion'];
                    $this->updates->host->probe((string)$completion['path']);
                    $this->finish((string)$completion['status'], (string)$completion['message'], (array)$completion['units']);
                    return;
                }
                $state->phase('recover', 'Восстанавливаем прежний код и проверенный снимок БД.');
                if (($operation['database_changed'] ?? false) && !($operation['snapshot_ready'] ?? false)) {
                    throw new RuntimeException('Нет завершённого исходного снимка. Автоматическое восстановление запрещено.');
                }
                if ($operation['snapshot_ready'] ?? false) {
                    $this->updates->recoveryModules(new ReleaseManifest((array)$operation['source']), $this->updates->database->info((string)$operation['source']['id']));
                    $this->updates->database->restore((string)$operation['source']['id']);
                }
                if (isset($operation['source_path'])) {
                    $this->updates->layout->switchTo((string)$operation['source']['id']);
                    $this->updates->host->probe((string)$operation['source_path']);
                }
                $state->save(['database_changed' => false, 'completion' => null]);
                if ($action === 'rollback') {
                    $this->finish('rolled_back', 'Прежняя версия и её база данных восстановлены.', (array)$operation['services']);
                    return;
                }
                // Исходная копия остаётся прежней: повтор не помечает частичную миграцию старой версией.
                $state->phase('retry', 'Исходное состояние восстановлено. Повторяем обновление.');
            }
            $manifest = new ReleaseManifest((array)$operation['target']);
            $state->phase('check', 'Повторно проверяем окружение и совместимость модулей.');
            $snapshot = $this->updates->database->info($manifest->id());
            if ($snapshot === [] && Comparator::lessThan((string)$manifest->data['version'], (string)$operation['source']['version'])) {
                throw new RuntimeException('Для старой версии отсутствует проверенный снимок БД.');
            }
            foreach ($this->updates->checks($manifest, $snapshot) as $check) {
                if (!$check['ok']) {
                    throw new RuntimeException($check['title'] . ': ' . $check['detail']);
                }
            }
            $this->updates->layout->initialize();
            $state->phase('prepare', 'Сохраняем прежний код и готовим отдельный каталог нового релиза.');
            $sourcePath = $operation['source_path'] ?? $this->updates->layout->preserve((array)$operation['source']);
            $state->save(['source_path' => $sourcePath]);
            $targetPath = $this->updates->layout->release($manifest->id());
            if (!is_file($targetPath . '/release.json')) {
                $state->phase('download', 'Скачиваем готовый релиз и проверяем контрольную сумму.');
                $download = $state->directory . '/' . bin2hex(random_bytes(8)) . '.zip';
                $this->updates->catalog->download((array)$operation['release'], $manifest, $download);
                $staging = $targetPath . '.prepare-' . bin2hex(random_bytes(6));
                (new ReleasePackage())->extract($download, $staging, $manifest);
                $this->updates->layout->attach($staging);
                if (!rename($staging, $targetPath)) {
                    throw new RuntimeException('Не удалось завершить подготовку релиза.');
                }
                UpdateFiles::syncDirectory(dirname($targetPath));
                unlink($download);
            }
            $this->updates->layout->attach($targetPath);
            $this->updates->host->probe($targetPath, false, true);
            if ($httpLock === null) {
                $httpLock = $this->quiesce();
            }
            if (!($state->raw()['snapshot_ready'] ?? false)) {
                $state->phase('backup', 'Создаём последний снимок MySQL для исходного релиза.');
                $this->updates->database->snapshot((string)$operation['source']['id'], $this->updates->inventory(), array_values(array_map('strval', (array)$state->raw()['services'])));
                $state->save(['snapshot_ready' => true, 'snapshot_at' => gmdate('c')]);
            }
            $state->phase('database', $snapshot !== [] ? 'Возвращаем базу к сохранённой дате выбранного релиза.' : 'Применяем миграции нового релиза.');
            $state->save(['database_changed' => true]);
            if ($snapshot !== []) {
                $this->updates->database->restore($manifest->id());
            }
            $this->updates->host->probe($targetPath, $snapshot === []);
            $state->phase('switch', 'Переключаем код на подготовленный релиз.');
            $this->updates->layout->switchTo($manifest->id());
            $state->phase('verify', 'Проверяем запуск нового ядра и возобновляем фоновые службы.');
            $state->save(['starting_services' => true]);
            $this->updates->host->probe($targetPath);
            $units = array_values(array_map('strval', (array)($snapshot['services'] ?? $state->raw()['services'])));
            $this->finish('succeeded', 'Обновление завершено. Система работает.', $units);
        } catch (Throwable $error) {
            $state->save(['status' => 'failed', 'error' => $error->getMessage(),
                'message' => 'Операция остановлена. Выберите повтор или возврат прежней версии.']);
            throw $error;
        } finally {
            if (is_resource($httpLock)) {
                fclose($httpLock);
            }
            fclose($lock);
        }
    }

    /** @return resource */
    private function quiesce()
    {
        $state = $this->updates->state;
        $operation = $state->raw();
        if (!($operation['services_captured'] ?? false)) {
            $state->save(['services' => $this->updates->host->services(true), 'services_captured' => true]);
        }
        $state->save(['maintenance' => true]);
        UpdateFiles::write($state->directory . '/maintenance.flag', (string)$operation['id']);
        $state->phase('stop', 'Закрываем запись, завершаем HTTP-запросы и останавливаем фоновые процессы.');
        $running = array_unique(array_merge($this->updates->host->services(true), array_values(array_map('strval', (array)$state->raw()['services']))));
        $this->updates->host->control('stop', array_values($running));
        $lock = fopen($state->directory . '/http.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Не удалось заблокировать HTTP-запись.');
        }
        $deadline = microtime(true) + 120;
        while (!flock($lock, LOCK_EX | LOCK_NB)) {
            if (microtime(true) > $deadline) {
                fclose($lock);
                throw new RuntimeException('Текущие HTTP-запросы не завершились за 120 секунд.');
            }
            usleep(100000);
        }
        return $lock;
    }

    /** @param list<string> $units */
    private function finish(string $status, string $message, array $units): void
    {
        $this->updates->state->save(['starting_services' => true]);
        $this->updates->host->control('start', $units);
        $missing = array_diff($units, $this->updates->host->services());
        if ($missing !== []) {
            $this->updates->host->control('stop', $units);
            throw new RuntimeException('После запуска не отвечают службы: ' . implode(', ', $missing));
        }
        $operation = $this->updates->state->raw();
        $path = $status === 'succeeded' ? $this->updates->layout->release((string)$operation['target']['id'])
            : (string)($operation['source_path'] ?? $this->updates->layout->root);
        // Завершённое состояние фиксируется ДО открытия записи, чтобы reboot не требовал нового импорта.
        $this->updates->state->save(['completion' => ['status' => $status, 'path' => $path, 'units' => $units, 'message' => $message]]);
        $this->updates->state->phase('complete', $message);
        $this->updates->state->save(['status' => $status, 'maintenance' => false, 'error' => '']);
        if (is_file($this->updates->state->directory . '/maintenance.flag') && !unlink($this->updates->state->directory . '/maintenance.flag')) {
            throw new RuntimeException('Не удалось открыть систему после проверки.');
        }
        try {
            $this->updates->host->restartAgent();
        } catch (Throwable) {
            $this->updates->state->save(['warning' => 'Ядро запущено, но агент не перезапустился. Проверьте laiot-agent.service.']);
        }
    }
}
