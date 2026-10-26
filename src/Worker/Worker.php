<?php

declare(strict_types=1);

namespace MajorDoMo\Worker;

use MajorDoMo\Modules\ModuleManager;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Worker одного модуля.
 *
 * Один процесс на модуль. Задачи берутся из `module.json`, выполняются
 * последовательно; каждая — один вызов метода без собственного цикла.
 * Таймаут задачи enforced через fork, если pcntl доступен.
 */
final class Worker
{
    private bool $stop = false;

    public function __construct(
        private readonly ModuleManager $modules,
        private readonly BackgroundTaskState $state,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, array{method:string,interval:int,timeout:int,enabled:bool}>
     */
    public function tasks(string $moduleId): array
    {
        $module = $this->modules->get($moduleId);
        if ($module === null) {
            throw new RuntimeException('Модуль не найден или не включён: ' . $moduleId);
        }
        $tasks = $module['tasks'] ?? [];
        if (!is_array($tasks)) {
            return [];
        }

        return $tasks;
    }

    public function run(string $moduleId, bool $once = false): int
    {
        $module = $this->modules->get($moduleId);
        if ($module === null) {
            throw new RuntimeException('Модуль не найден или не включён: ' . $moduleId);
        }
        $tasks = $this->tasks($moduleId);
        $tasks = array_filter($tasks, static fn (array $task): bool => $task['enabled'] === true);
        if ($tasks === []) {
            $this->logger->info('Worker: у модуля нет включённых задач', ['module' => $moduleId]);

            return 0;
        }

        $entrypoint = $module['entrypoint'] ?? null;
        if (!is_string($entrypoint) || $entrypoint === '') {
            throw new RuntimeException('Для tasks нужен entrypoint модуля: ' . $moduleId);
        }
        $class = (string)$module['namespace'] . $entrypoint;
        $instance = $this->modules->context($moduleId)->container()->make($class);
        foreach ($tasks as $name => $task) {
            if (!is_callable([$instance, $task['method']])) {
                throw new RuntimeException('Метод задачи не найден: ' . $moduleId . '.' . $task['method']);
            }
        }

        $this->installSignals();
        $pid = getmypid() ?: 0;
        if (!$this->state->acquire($moduleId, $pid)) {
            throw new RuntimeException('Worker уже запущен для модуля: ' . $moduleId);
        }
        try {
            $next = [];
            $now = microtime(true);
            foreach ($tasks as $name => $task) {
                $next[$name] = $now;
            }

            while (!$this->stop) {
                $this->state->refresh($moduleId, $pid);
                $due = [];
                $now = microtime(true);
                foreach ($tasks as $name => $task) {
                    if ($next[$name] <= $now) {
                        $due[] = $name;
                    }
                }
                foreach ($due as $name) {
                    $task = $tasks[$name];
                    $this->runTask($moduleId, $name, $task, $instance);
                    $next[$name] = microtime(true) + $task['interval'];
                }
                if ($once) {
                    break;
                }
                if ($due === []) {
                    $closest = min($next);
                    $sleep = max(0.2, min(1.0, $closest - microtime(true)));
                    usleep((int)($sleep * 1_000_000));
                }
            }
        } finally {
            $this->state->release($moduleId, $pid);
        }

        return 0;
    }

    /**
     * @param array{method:string,interval:int,timeout:int,enabled:bool} $task
     */
    private function runTask(string $moduleId, string $taskName, array $task, object $instance): void
    {
        $started = microtime(true);
        $nextRun = date('Y-m-d H:i:s', (int)($started + $task['interval']));
        $this->state->start($moduleId, $taskName, getmypid() ?: 0, $nextRun);

        $context = new BackgroundTaskContext(
            $this->modules->context($moduleId),
            $taskName,
            $task['method'],
            $started,
            $task['timeout'],
            fn (): bool => $this->stop
        );

        $error = null;
        try {
            $this->execute($instance, $task['method'], $context);
            $this->logger->info('Worker: задача выполнена', ['module' => $moduleId, 'task' => $taskName]);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            $this->logger->error('Worker: задача упала', [
                'module' => $moduleId,
                'task' => $taskName,
                'error' => $error,
            ]);
        }
        $this->state->finish(
            $moduleId,
            $taskName,
            $error === null ? 'ok' : 'error',
            $error,
            date('Y-m-d H:i:s', (int)(microtime(true) + $task['interval']))
        );
    }

    private function execute(object $instance, string $method, BackgroundTaskContext $context): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
            $instance->{$method}($context);

            return;
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Не удалось создать процесс задачи.');
        }
        if ($pid === 0) {
            try {
                $instance->{$method}($context);
                exit(0);
            } catch (Throwable $exception) {
                fwrite(STDERR, $exception->getMessage() . PHP_EOL);
                exit(1);
            }
        }

        $deadline = $context->deadline();
        while (true) {
            $result = pcntl_waitpid($pid, $status, WNOHANG);
            if ($result === $pid) {
                break;
            }
            if (microtime(true) >= $deadline) {
                posix_kill($pid, SIGKILL);
                pcntl_waitpid($pid, $status);
                throw new RuntimeException('Задача превысила timeout ' . $context->timeout . ' с.');
            }
            usleep(100_000);
        }
        if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
            throw new RuntimeException('Процесс задачи завершился с ошибкой.');
        }
    }

    private function installSignals(): void
    {
        if (!function_exists('pcntl_async_signals') || !function_exists('pcntl_signal')) {
            return;
        }
        pcntl_async_signals(true);
        $stop = function (): void {
            $this->stop = true;
        };
        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);
    }
}
