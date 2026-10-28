<?php

declare(strict_types=1);

namespace MajorDoMo\Worker;

use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Events\PropertyEventQueue;
use MajorDoMo\Logging\ErrorRepository;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Queue\JobRegistry;
use MajorDoMo\Queue\Queue;
use MajorDoMo\Queue\QueueWorker;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Worker одного модуля.
 *
 * Один процесс на модуль. Задачи берутся из `module.json`, выполняются
 * последовательно; каждая — один вызов метода без собственного цикла.
 * Таймаут задачи enforced через fork, если pcntl доступен.
 *
 * В том же процессе обслуживается очередь `module.<id>`: доставка изменений
 * свойств этому модулю. Медленный подписчик задерживает только свой worker,
 * а не события свойств и не другие модули.
 *
 * 2026-10-27 — отдельный процесс привязок получает исключительную очередь.
 * 2026-10-27 — worker обслуживает очередь доставки модуля.
 * 2026-10-27 — блокировка процесса удерживается при длительных обработчиках.
 * 2026-10-27 — PDO закрывается до fork: ребёнок не наследует сокет родителя.
 * 2026-10-27 — SIGTERM родителя пересылается дочерней задаче.
 */
final class Worker
{
    private bool $stop = false;

    private ?QueueWorker $queueWorker = null;

    public function __construct(
        private readonly ModuleManager $modules,
        private readonly BackgroundTaskState $state,
        private readonly Queue $queue,
        private readonly JobRegistry $jobs,
        private readonly PropertyEventQueue $eventQueues,
        private readonly LoggerInterface $logger,
        private readonly ProcessLock $processLock,
        private readonly ProcessLock $deliveryLock,
        private readonly ErrorRepository $errors,
        private readonly DatabaseManager $database
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
        $instance = null;
        if ($tasks !== []) {
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
        }

        $this->installSignals();
        $pid = getmypid() ?: 0;
        if (!$this->processLock->acquire(['module.' . $moduleId])) {
            throw new RuntimeException('Worker уже запущен для модуля: ' . $moduleId);
        }
        $stateAcquired = false;
        try {
            $stateAcquired = $this->state->acquire($moduleId, $pid);
            if (!$stateAcquired) {
                // ProcessLock уже удерживается: это остаток жёсткого рестарта,
                // а не второй worker. TTL кэш-ключа ждать бессмысленно.
                $this->logger->warning('Worker: перехват устаревшей блокировки состояния', [
                    'module' => $moduleId,
                ]);
                $this->state->takeover($moduleId, $pid);
                $stateAcquired = true;
            }
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
                    if ($instance === null) {
                        break;
                    }
                    $task = $tasks[$name];
                    $this->runTask($moduleId, $name, $task, $instance, $once);
                    $next[$name] = microtime(true) + $task['interval'];
                }
                $worked = $this->drainDeliveries($moduleId) > 0;
                if ($once) {
                    while ($this->drainDeliveries($moduleId) > 0) {
                        // Догружаем всё, что уже накопилось для модуля.
                    }
                    break;
                }
                if ($due === [] && !$worked) {
                    $closest = $next === [] ? microtime(true) + 1.0 : min($next);
                    $sleep = max(0.2, min(1.0, $closest - microtime(true)));
                    usleep((int)($sleep * 1_000_000));
                }
            }
        } finally {
            try {
                if ($stateAcquired) {
                    $this->state->release($moduleId, $pid);
                }
            } finally {
                $this->processLock->release();
            }
        }

        return 0;
    }

    /**
     * Обслуживает очередь доставки свойств этого модуля.
     *
     * @return int Сколько заданий выполнено за проход.
     */
    private function drainDeliveries(string $moduleId): int
    {
        $worker = $this->queueWorker ??= new QueueWorker($this->queue, $this->jobs, $this->logger, $this->errors);
        $queue = $this->eventQueues->moduleQueueName($moduleId);
        if (!$this->deliveryLock->acquire(['queue.' . $queue])) {
            return 0;
        }
        $handled = 0;
        try {
            while ($handled < 100 && $worker->work($queue)) {
                $handled++;
            }
        } finally {
            $this->deliveryLock->release();
        }

        return $handled;
    }

    /**
     * @param array{method:string,interval:int,timeout:int,enabled:bool} $task
     */
    private function runTask(string $moduleId, string $taskName, array $task, object $instance, bool $once): void
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
            fn (): bool => $this->stop,
            $once
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

        // PDO родителя нельзя наследовать дочернему процессу: два процесса на
        // одном TCP-сокете сдвигают состояние протокола, и следующий запрос
        // worker-а после fork получает чужие ответы. Закрываем соединение до
        // fork; оба процесса откроют своё при первом запросе. SQLite в памяти
        // не трогаем — данные принадлежат процессу.
        $this->disconnectBeforeFork();

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
        $forwarded = false;
        while (true) {
            if ($this->stop && !$forwarded) {
                // Docker stop приходит только PID 1 (этому процессу). Задаче
                // нужно передать SIGTERM, иначе её длинный цикл (MQTT)
                // останется сиротой и будет держать блокировки.
                posix_kill($pid, SIGTERM);
                $forwarded = true;
            }
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

    private function disconnectBeforeFork(): void
    {
        try {
            if (!$this->database->isConfigured()) {
                return;
            }
            $config = $this->database->config();
            if ((string)($config['driver'] ?? '') === 'sqlite'
                && (string)($config['database'] ?? '') === ':memory:') {
                return;
            }
            $this->database->connection()->disconnect();
        } catch (Throwable) {
            // Соединение ещё не открывалось: делить с ребёнком нечего.
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
