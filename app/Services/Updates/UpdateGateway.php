<?php

declare(strict_types=1);

namespace LaIOT\Services\Updates;

/**
 * Аварийный экран работает до Composer, сессии и БД. Доступ — отдельный случайный ключ операции.
 * 2026-10-29 — переживает переключение кода и перезагрузку; POST выбирает повтор или возврат.
 */
final class UpdateGateway
{
    private static bool $entered = false;

    public static function enter(string $root): void
    {
        if (self::$entered) {
            return;
        }
        self::$entered = true;
        require_once __DIR__ . '/UpdateFiles.php';
        require_once __DIR__ . '/UpdateState.php';
        $directory = (realpath($root . '/var') ?: $root . '/var') . '/updates';
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if ($path === '/admin/updates/recovery') {
            self::recovery(new UpdateState($directory));
            exit;
        }
        if (is_file($directory . '/maintenance.flag')) {
            self::unavailable();
        }
        if (is_dir($directory)) {
            $lock = fopen($directory . '/http.lock', 'c');
            if ($lock === false || !flock($lock, LOCK_SH | LOCK_NB)) {
                self::unavailable();
            }
            register_shutdown_function(static function () use ($lock): void {
                fclose($lock);
            });
            // Флаг мог появиться между первой проверкой и получением блокировки.
            if (is_file($directory . '/maintenance.flag')) {
                self::unavailable();
            }
        }
    }

    private static function unavailable(): never
    {
        http_response_code(503);
        header('Retry-After: 15');
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LaIOT — обновление</title><h1>Обновление LaIOT</h1><p>Запись временно остановлена. Дождитесь завершения операции.</p><a href="/admin/updates/recovery">Ход обновления и восстановление</a></html>';
        exit;
    }

    private static function recovery(UpdateState $state): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'none'; style-src 'self'; font-src 'self'; img-src 'self'; script-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        $operation = $state->read();
        $key = (string)($_COOKIE['laiot_update'] ?? '');
        $error = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['recovery_key']) && is_string($_POST['recovery_key'])) {
            $key = trim($_POST['recovery_key']);
        }
        $authorized = preg_match('/^[a-f0-9]{64}$/D', $key) === 1
            && hash_equals((string)($operation['access'] ?? ''), hash('sha256', $key));
        if ($authorized) {
            setcookie('laiot_update', $key, ['expires' => time() + 7 * 86400, 'path' => '/admin/updates/recovery',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Strict']);
        }
        if ($authorized && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
            $lock = null;
            try {
                $lock = $state->lock('request');
                $operation = $state->read();
                $action = $_POST['action'];
                if (!is_string($action) || !in_array($action, ['retry', 'rollback'], true)
                    || !is_string($_POST['csrf'] ?? null) || !hash_equals(hash_hmac('sha256', 'recover', $key), $_POST['csrf'])
                    || !is_string($_POST['operation'] ?? null) || $_POST['operation'] !== ($operation['id'] ?? '')
                    || !in_array($operation['status'] ?? '', ['interrupted', 'failed'], true) || $state->locked()) {
                    throw new \RuntimeException('Действие недоступно или запрос устарел. Обновите страницу.');
                }
                if (($_POST['confirmed'] ?? '') !== '1') {
                    throw new \RuntimeException('Подтвердите восстановление данных.');
                }
                $state->save(['status' => 'queued', 'action' => $action, 'message' => 'Ожидаем запуска восстановления.']);
                $socket = stream_socket_client('unix:///run/laiot/agent.sock', $code, $message, 5);
                if ($socket === false) {
                    throw new \RuntimeException('Агент недоступен. Восстановите его службу и повторите попытку.');
                }
                stream_set_timeout($socket, 10);
                fwrite($socket, "{\"action\":\"update.start\"}\n");
                $reply = json_decode((string)fgets($socket), true);
                fclose($socket);
                if (!is_array($reply) || ($reply['ok'] ?? false) !== true) {
                    throw new \RuntimeException('Служба восстановления не запустилась. Проверьте laiot-agent и laiot-update.service.');
                }
                header('Location: /admin/updates/recovery', true, 303);
                return;
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
                if (($state->raw()['status'] ?? '') === 'queued' && !$state->locked()) {
                    $state->save(['status' => 'failed', 'error' => $error]);
                }
            } finally {
                if (is_resource($lock)) {
                    fclose($lock);
                }
            }
        }
        $operation = $state->read();
        $status = (string)($operation['status'] ?? 'none');
        $progress = self::progress($operation);
        if (($_GET['status'] ?? '') === '1') {
            header('Content-Type: application/json; charset=utf-8');
            if (!$authorized) {
                http_response_code(403);
                echo '{"error":"access_required"}';
                return;
            }
            echo json_encode($progress, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            return;
        }
        $escape = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $labels = ['queued' => 'Ожидаем запуска', 'running' => 'Обновление выполняется', 'interrupted' => 'Обновление было прервано',
            'failed' => 'Требуется решение', 'succeeded' => 'Обновление завершено', 'rolled_back' => 'Прежняя версия восстановлена', 'none' => 'Операций пока нет'];
        $theme = ($_COOKIE['theme'] ?? '') === 'dark' ? 'dark' : 'light';
        require dirname(__DIR__, 3) . '/resources/views/updates/recovery.php';
    }

    /** @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private static function progress(array $operation): array
    {
        $steps = ['check' => 1, 'prepare' => 2, 'download' => 2, 'stop' => 3, 'backup' => 4, 'database' => 5, 'switch' => 6, 'verify' => 7, 'complete' => 8];
        return [
            'id' => (string)($operation['id'] ?? ''), 'status' => (string)($operation['status'] ?? 'none'),
            'phase' => (string)($operation['phase'] ?? ''), 'step' => $steps[$operation['phase'] ?? ''] ?? 0,
            'message' => (string)($operation['message'] ?? ''), 'error' => (string)($operation['error'] ?? ''),
            'warning' => (string)($operation['warning'] ?? ''), 'snapshot_at' => (string)($operation['snapshot_at'] ?? ''),
            'events' => array_map(static fn (array $event): array => ['at' => (string)$event['at'], 'message' => (string)$event['message']], (array)($operation['events'] ?? [])),
        ];
    }
}
