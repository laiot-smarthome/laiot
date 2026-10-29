<?php

declare(strict_types=1);

namespace LaIOT\Services\WebSocket;

use LaIOT\Foundation\Application;
use LaIOT\Services\Worker\BackgroundTaskState;
use RuntimeException;
use Throwable;
use Workerman\Timer;
use Workerman\Worker;

/**
 * Запуск WebSocket-сервера Workerman.
 *
 * 2026-10-29 — core-worker websocket, общий статус/heartbeat и foreground.
 * Application собирается внутри дочернего процесса: соединение с БД не
 * должно наследоваться после fork. Родительский процесс CLI перед запуском
 * отключает своё соединение и передаёт только конфигурацию.
 *
 * 2026-10-29 — отдельный процессный сервер, конфигурация из приложения.
 */
final class WebSocketServer
{
    public function __construct(private readonly Application $app)
    {
    }

    public function run(int $masterPid): void
    {
        $root = $this->app->root();
        $appConfig = $this->app->configuration();
        $config = WebSocketConfig::fromArray((array)($appConfig['websocket'] ?? []));
        if (!$config->enabled) {
            throw new RuntimeException('WebSocket выключен: WEBSOCKET_ENABLED=false.');
        }
        $runtimeDirectory = $root . '/var/websocket';
        if (!is_dir($runtimeDirectory) && !mkdir($runtimeDirectory, 0775, true) && !is_dir($runtimeDirectory)) {
            throw new \RuntimeException('Не удалось создать каталог websocket: ' . $runtimeDirectory);
        }

        Worker::$pidFile = $runtimeDirectory . '/websocket.pid';
        // Служба запускается только в foreground: жизненным циклом управляют
        // systemd и общий core-агент, не отдельные команды Workerman.
        Worker::$statusFile = $runtimeDirectory . '/workerman.status';
        Worker::$logFile = $root . '/var/log/websocket-workerman.log';
        Worker::$stdoutFile = $root . '/var/log/websocket-stdout.log';
        Worker::$daemonize = false;

        $worker = new Worker($config->listenAddress());
        $worker->name = 'laiot-websocket';
        // Workerman на Windows не поддерживает несколько процессов в одном
        // файле; production — Linux, где число процессов задаётся настройкой.
        $worker->count = DIRECTORY_SEPARATOR === '/' ? $config->workers : 1;
        $worker->reusePort = true;
        $worker->onWorkerStart = static function (Worker $child) use ($root, $appConfig, $masterPid): void {
            try {
                $app = new Application($root, $appConfig);
                $app->boot();
                $app->container()->make(WebSocketRuntime::class)->boot($child);
                if ($child->id === 0) {
                    $state = $app->container()->make(BackgroundTaskState::class);
                    Timer::add(10, static fn () => $state->refreshTask('core', 'websocket', $masterPid));
                }
            } catch (Throwable $error) {
                Worker::safeEcho('WebSocket: запуск не удался: ' . $error->getMessage() . PHP_EOL);
                Worker::stopAll(250, $error);
            }
        };

        // CoreWorker уже записал общий статус. Workerman завершает master
        // через exit, поэтому обычный finally CoreWorker не выполнится.
        $state = $this->app->container()->make(BackgroundTaskState::class);
        register_shutdown_function(static function () use ($state, $masterPid): void {
            if (getmypid() === $masterPid && ($state->get('core', 'websocket')['status'] ?? null) === 'running') {
                $last = error_get_last();
                $fatal = $last !== null && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
                $state->finish('core', 'websocket', $fatal ? 'error' : 'ok', $fatal ? $last['message'] : null, date('Y-m-d H:i:s'));
                $state->releaseTask('core', 'websocket', $masterPid);
            }
        });
        $this->app->database()->disconnect();
        global $argv;
        $previousArgv = $argv;
        $previousServerArgv = $_SERVER['argv'] ?? [];
        $argv = [$argv[0], 'start'];
        $_SERVER['argv'] = $argv;
        try {
            Worker::runAll();
        } finally {
            $argv = $previousArgv;
            $_SERVER['argv'] = $previousServerArgv;
        }
    }
}
