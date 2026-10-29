<?php

declare(strict_types=1);

namespace LaIOT\Services\Systemd;

use LaIOT\Services\Worker\CoreWorker;
use RuntimeException;

/**
 * Привилегированные операции systemd для worker-модулей.
 *
 * 2026-10-29 — поиск модулей следует current после обновления прямой установки.
 * PHP-пользователь не вызывает systemctl напрямую: только агент. Все имена
 * юнитов строятся из проверенного id модуля, без shell-строк.
 * 2026-10-29 — общий список core-задач, защищённые юниты и прямая раскладка.
 */
final readonly class SystemdController
{
    public const CORE_TASKS = CoreWorker::TASKS;

    public function __construct(
        private ProcessRunner $runner,
        private string $root,
        private string $user = 'laiot',
        private string $group = 'laiot',
        private string $systemctl = 'systemctl'
    ) {
    }

    public function start(string $module): ProcessResult
    {
        return $this->action('start', $module);
    }

    public function stop(string $module): ProcessResult
    {
        $this->assertModuleId($module);

        return $this->runner->run([$this->systemctl, 'stop', $this->unit($module)]);
    }

    public function restart(string $module): ProcessResult
    {
        return $this->action('restart', $module);
    }

    /**
     * @return array{module:string,active:string,sub:string,pid:int,started:string}
     */
    public function status(string $module): array
    {
        $this->assertModuleId($module);

        return $this->statusUnit($this->unit($module), $module);
    }

    /**
     * @param list<string> $enabledModules
     * @return array{started:list<string>,stopped:list<string>}
     */
    public function sync(array $enabledModules): array
    {
        $desired = [];
        foreach ($enabledModules as $module) {
            $this->assertModule($module);
            $desired[$module] = true;
        }

        $started = [];
        $stopped = [];
        $list = $this->runner->run([
            $this->systemctl,
            'list-units',
            'laiot-worker@*',
            '--all',
            '--no-legend',
            '--plain',
        ]);
        if (!$list->successful()) {
            throw new RuntimeException('systemctl list-units не удался: ' . trim($list->stderr));
        }
        foreach (preg_split('/\R/', $list->stdout) ?: [] as $line) {
            if (!preg_match('/laiot-worker@([a-z][a-z0-9_]{1,63})\.service/', $line, $match)) {
                continue;
            }
            if (!isset($desired[$match[1]])) {
                $result = $this->stop($match[1]);
                if ($result->successful()) {
                    $stopped[] = $match[1];
                }
            }
        }
        foreach (array_keys($desired) as $module) {
            $result = $this->start($module);
            if ($result->successful()) {
                $started[] = $module;
            }
        }

        return ['started' => $started, 'stopped' => $stopped];
    }

    public function coreStart(string $task): ProcessResult
    {
        return $this->coreAction('start', $task);
    }

    public function coreStop(string $task): ProcessResult
    {
        return $this->coreAction('stop', $task);
    }

    public function coreRestart(string $task): ProcessResult
    {
        return $this->coreAction('restart', $task);
    }

    /**
     * @return array{module:string,active:string,sub:string,pid:int,started:string}
     */
    public function coreStatus(string $task): array
    {
        $this->assertCoreTask($task);

        return $this->statusUnit('laiot-core@' . $task, 'core:' . $task);
    }

    /**
     * @param list<string> $tasks
     * @return array{started:list<string>,stopped:list<string>}
     */
    public function syncCore(array $tasks): array
    {
        $desired = [];
        foreach ($tasks as $task) {
            $this->assertCoreTask($task);
            $desired[$task] = true;
        }
        $started = [];
        $stopped = [];
        $list = $this->runner->run([
            $this->systemctl,
            'list-units',
            'laiot-core@*',
            '--all',
            '--no-legend',
            '--plain',
        ]);
        if (!$list->successful()) {
            throw new RuntimeException('systemctl list-units core не удался: ' . trim($list->stderr));
        }
        foreach (preg_split('/\R/', $list->stdout) ?: [] as $line) {
            if (!preg_match('/laiot-core@([a-z][a-z0-9_.-]{1,63})\.service/', $line, $match)) {
                continue;
            }
            if (!isset($desired[$match[1]]) && $this->isCoreTask($match[1])) {
                if ($this->coreStop($match[1])->successful()) {
                    $stopped[] = $match[1];
                }
            }
        }
        foreach (array_keys($desired) as $task) {
            if ($this->coreStart($task)->successful()) {
                $started[] = $task;
            }
        }

        return ['started' => $started, 'stopped' => $stopped];
    }

    public function installCoreUnit(string $targetDirectory = '/etc/systemd/system', ?string $template = null): string
    {
        $template ??= $this->root . '/deploy/systemd/laiot-core@.service';

        return $this->installTemplate($template, $targetDirectory, 'laiot-core@.service');
    }

    /**
     * @return array{worker:string,core:string}
     */
    public function installAllUnits(string $targetDirectory = '/etc/systemd/system'): array
    {
        return [
            'worker' => $this->installUnit($targetDirectory),
            'core' => $this->installCoreUnit($targetDirectory),
        ];
    }

    /**
     * Агент не читает root-шаблоны из доступного PHP каталога приложения:
     * иначе захват сайта позволил бы записать произвольный root-unit.
     * @return array{worker:string,core:string}
     */
    public function installedUnits(string $targetDirectory = '/etc/systemd/system'): array
    {
        $units = ['worker' => rtrim($targetDirectory, '/') . '/laiot-worker@.service',
            'core' => rtrim($targetDirectory, '/') . '/laiot-core@.service'];
        foreach ($units as $file) {
            clearstatcache(true, $file);
            if (!is_file($file) || fileowner($file) !== 0 || ((int)fileperms($file) & 0022) !== 0) {
                throw new RuntimeException('Нет защищённого юнита ' . $file . '. Выполните deploy/setup-ubuntu.sh от root.');
            }
        }
        return $units;
    }

    public function installUnit(string $targetDirectory = '/etc/systemd/system', ?string $template = null): string
    {
        $template ??= $this->root . '/deploy/systemd/laiot-worker@.service';

        return $this->installTemplate($template, $targetDirectory, 'laiot-worker@.service');
    }

    /**
     * @return array{module:string,active:string,sub:string,pid:int,started:string}
     */
    private function statusUnit(string $unit, string $label): array
    {
        $result = $this->runner->run([
            $this->systemctl,
            'show',
            $unit,
            '--no-pager',
            '--property=ActiveState,SubState,MainPID,ExecMainStartTimestamp',
        ]);
        if (!$result->successful()) {
            throw new RuntimeException('systemctl show не удался: ' . trim($result->stderr));
        }
        $values = [];
        foreach (preg_split('/\R/', $result->stdout) ?: [] as $line) {
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $values[$key] = trim($value);
            }
        }

        return [
            'module' => $label,
            'active' => $values['ActiveState'] ?? 'unknown',
            'sub' => $values['SubState'] ?? 'unknown',
            'pid' => (int)($values['MainPID'] ?? 0),
            'started' => $values['ExecMainStartTimestamp'] ?? '',
        ];
    }

    private function coreAction(string $action, string $task): ProcessResult
    {
        if (!in_array($action, ['start', 'stop', 'restart'], true)) {
            throw new RuntimeException('Недопустимое действие core-worker.');
        }
        $this->assertCoreTask($task);

        return $this->runner->run([$this->systemctl, $action, 'laiot-core@' . $task]);
    }

    private function assertCoreTask(string $task): void
    {
        if (!$this->isCoreTask($task)) {
            throw new RuntimeException('Недопустимый core-worker: ' . $task);
        }
    }

    /** Базовые процессы и полосы очереди событий (`events.<n>`). */
    private function isCoreTask(string $task): bool
    {
        if (in_array($task, self::CORE_TASKS, true) || preg_match('/^events\.(0|[1-9][0-9]?)$/D', $task) === 1) {
            return true;
        }
        if (preg_match('/^module\.([a-z][a-z0-9_]{1,63})$/D', $task, $match) !== 1) {
            return false;
        }
        $root = realpath(is_dir($this->root . '/current') ? $this->root . '/current' : $this->root);
        return $root !== false && (is_dir($root . '/modules/core/' . $match[1]) || is_dir($root . '/modules/' . $match[1]));
    }

    private function action(string $action, string $module): ProcessResult
    {
        if (!in_array($action, ['start', 'restart'], true)) {
            throw new RuntimeException('Недопустимое действие worker.');
        }
        $this->assertModule($module);

        return $this->runner->run([$this->systemctl, $action, $this->unit($module)]);
    }

    private function assertModuleId(string $module): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $module)) {
            throw new RuntimeException('Некорректный id модуля.');
        }
    }

    private function assertModule(string $module): void
    {
        $this->assertModuleId($module);
        $root = realpath(is_dir($this->root . '/current') ? $this->root . '/current' : $this->root);
        if ($root === false) {
            throw new RuntimeException('Корень LaIOT не найден.');
        }
        $core = $root . '/modules/core/' . $module;
        $third = $root . '/modules/' . $module;
        if (!is_dir($core) && !is_dir($third)) {
            throw new RuntimeException('Модуль не найден: ' . $module);
        }
    }

    private function unit(string $module): string
    {
        return 'laiot-worker@' . $module;
    }

    private function installTemplate(string $template, string $targetDirectory, string $targetName): string
    {
        if (!is_file($template)) {
            throw new RuntimeException('Шаблон systemd не найден: ' . $template);
        }
        $content = (string)file_get_contents($template);
        $content = str_replace(
            ['@@ROOT@@', '@@USER@@', '@@GROUP@@'],
            [$this->root, $this->user, $this->group],
            $content
        );
        if (is_file($this->root . '/bin/laiot')) {
            $content = str_replace(
                ['ReadWritePaths=' . $this->root . '/shared ' . $this->root . '/current/var', $this->root . '/current'],
                ['ReadWritePaths=' . $this->root . '/var', $this->root],
                $content
            );
        }
        $content = str_replace('/usr/bin/php ', PHP_BINARY . ' ', $content);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Не удалось создать каталог systemd: ' . $targetDirectory);
        }
        $target = rtrim($targetDirectory, '/') . '/' . $targetName;
        if (file_put_contents($target, $content) === false) {
            throw new RuntimeException('Не удалось записать unit: ' . $target);
        }
        $result = $this->runner->run([$this->systemctl, 'daemon-reload']);
        if (!$result->successful()) {
            throw new RuntimeException('systemctl daemon-reload не удался: ' . trim($result->stderr));
        }

        return $target;
    }
}
