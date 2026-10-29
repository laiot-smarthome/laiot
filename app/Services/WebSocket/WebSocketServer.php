<?php

declare(strict_types=1);

namespace LaIOT\Services\WebSocket;

use LaIOT\Foundation\Application;
use Throwable;
use Workerman\Worker;

/**
 * Запуск WebSocket-сервера Workerman.
 *
 * Application собирается внутри дочернего процесса: соединение с БД не
 * должно наследоваться после fork. Родительский процесс CLI перед запуском
 * отключает своё соединение и передаёт только конфигурацию.
 *
 * 2026-10-29 — отдельный процессный сервер, конфигурация из приложения.
 */
final class WebSocketServer
{
    /**
     * @param array<string, mixed> $appConfig
     */
    public static function run(string $root, array $appConfig, WebSocketConfig $config, bool $daemon = false): void
    {
        $runtimeDirectory = $root . '/var/websocket';
        if (!is_dir($runtimeDirectory) && !mkdir($runtimeDirectory, 0775, true) && !is_dir($runtimeDirectory)) {
            throw new \RuntimeException('Не удалось создать каталог websocket: ' . $runtimeDirectory);
        }

        Worker::$pidFile = $runtimeDirectory . '/websocket.pid';
        // Свой status-файл, чтобы `websocket status` не пересекался с другими
        // Workerman-приложениями на том же сервере.
        Worker::$statusFile = $runtimeDirectory . '/workerman.status';
        Worker::$logFile = $root . '/var/log/websocket-workerman.log';
        Worker::$stdoutFile = $root . '/var/log/websocket-stdout.log';
        Worker::$daemonize = $daemon;

        $worker = new Worker($config->listenAddress());
        $worker->name = 'laiot-websocket';
        // Workerman на Windows не поддерживает несколько процессов в одном
        // файле; production — Linux, где число процессов задаётся настройкой.
        $worker->count = DIRECTORY_SEPARATOR === '/' ? $config->workers : 1;
        $worker->reusePort = true;
        $worker->onWorkerStart = static function (Worker $child) use ($root, $appConfig): void {
            try {
                $app = new Application($root, $appConfig);
                $app->boot();
                $app->container()->make(WebSocketRuntime::class)->boot($child);
            } catch (Throwable $error) {
                Worker::safeEcho('WebSocket: запуск не удался: ' . $error->getMessage() . PHP_EOL);
                Worker::stopAll(250, $error);
            }
        };

        Worker::runAll();
    }
}
