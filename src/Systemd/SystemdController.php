<?php

declare(strict_types=1);

namespace MajorDoMo\Systemd;

use RuntimeException;

/**
 * Привилегированные операции systemd для worker-модулей.
 *
 * PHP-пользователь не вызывает systemctl напрямую: только агент. Все имена
 * юнитов строятся из проверенного id модуля, без shell-строк.
 */
final readonly class SystemdController
{
    public const CORE_TASKS = ['queue', 'timeouts'];

    public function __construct(
        private ProcessRunner $runner,
        private string $root,
        private string $user = 'mydomo',
        private string $group = 'mydomo',
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
            'mydomo-worker@*',
            '--all',
            '--no-legend',
            '--plain',
        ]);
        if (!$list->successful()) {
            throw new RuntimeException('systemctl list-units не удался: ' . trim($list->stderr));
        }
        foreach (preg_split('/\R/', $list->stdout) ?: [] as $line) {
            if (!preg_match('/mydomo-worker@([a-z][a-z0-9_]{1,63})\.service/', $line, $match)) {
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

        return $this->statusUnit('mydomo-core@' . $task, 'core:' . $task);
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
            'mydomo-core@*',
            '--all',
            '--no-legend',
            '--plain',
        ]);
        if (!$list->successful()) {
            throw new RuntimeException('systemctl list-units core не удался: ' . trim($list->stderr));
        }
        foreach (preg_split('/\R/', $list->stdout) ?: [] as $line) {
            if (!preg_match('/mydomo-core@([a-z][a-z0-9_]{1,63})\.service/', $line, $match)) {
                continue;
            }
            if (!isset($desired[$match[1]]) && in_array($match[1], self::CORE_TASKS, true)) {
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
        $template ??= $this->root . '/deploy/systemd/mydomo-core@.service';

        return $this->installTemplate($template, $targetDirectory, 'mydomo-core@.service');
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

    public function installUnit(string $targetDirectory = '/etc/systemd/system', ?string $template = null): string
    {
        $template ??= $this->root . '/deploy/systemd/mydomo-worker@.service';

        return $this->installTemplate($template, $targetDirectory, 'mydomo-worker@.service');
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

        return $this->runner->run([$this->systemctl, $action, 'mydomo-core@' . $task]);
    }

    private function assertCoreTask(string $task): void
    {
        if (!in_array($task, self::CORE_TASKS, true)) {
            throw new RuntimeException('Недопустимый core-worker: ' . $task);
        }
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
        $root = realpath($this->root);
        if ($root === false) {
            throw new RuntimeException('Корень MyDoMo не найден.');
        }
        $core = $root . '/modules/core/' . $module;
        $third = $root . '/modules/' . $module;
        if (!is_dir($core) && !is_dir($third)) {
            throw new RuntimeException('Модуль не найден: ' . $module);
        }
    }

    private function unit(string $module): string
    {
        return 'mydomo-worker@' . $module;
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
