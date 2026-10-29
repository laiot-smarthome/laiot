<?php

declare(strict_types=1);

namespace MajorDoMo\Http;

use ErrorException;
use MajorDoMo\Foundation\Config;
use Throwable;

/**
 * Последняя HTTP-граница, устанавливаемая перед Composer и bootstrap.
 *
 * Не обращается к сервисам приложения: при сбое загрузки они недоступны.
 * Обычные warning остаются в журнале PHP, фатальные ошибки ловит shutdown.
 *
 * Изменения:
 * 2026-10-29 — ранний перехват исключений и fatal с резервом памяти.
 */
final class EarlyErrorHandler
{
    private bool $handled = false;
    private int $bufferLevel = 0;
    private string $memoryReserve = '';

    public function __construct(private readonly string $root)
    {
    }

    public function register(): void
    {
        // Нативный вывод обходит APP_DEBUG и может раскрыть детали до bootstrap.
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
        set_exception_handler(function (Throwable $error): never {
            $this->respond($error);
            exit(1);
        });
        register_shutdown_function(function (): void {
            // При исчерпании памяти резерв нужен уже для создания исключения.
            unset($this->memoryReserve);
            $last = error_get_last();
            if ($last !== null && in_array($last['type'], [
                E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR,
            ], true)) {
                $this->respond(new ErrorException($last['message'], 0, $last['type'], $last['file'], $last['line']), false);
            }
        });
        $this->memoryReserve = str_repeat(' ', 512 * 1024);
        $this->bufferLevel = ob_get_level();
        ob_start();
    }

    private function respond(Throwable $error, bool $log = true): void
    {
        if ($this->handled) {
            return;
        }
        $this->handled = true;
        unset($this->memoryReserve);
        while (ob_get_level() > $this->bufferLevel) {
            $buffer = ob_get_status();
            if (($buffer['flags'] & PHP_OUTPUT_HANDLER_REMOVABLE) === 0) {
                break;
            }
            ob_end_clean();
        }

        $providedId = (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
        $requestId = preg_match('/^[A-Za-z0-9._-]{1,64}$/D', $providedId) === 1
            ? $providedId : bin2hex(random_bytes(8));
        if ($log) {
            error_log('MyDoMo request_id=' . $requestId . ' ' . $error);
        }

        // До загрузки Config безопасное значение — false. Сам обработчик
        // не перечитывает .env и не пытается повторно загрузить приложение.
        $debug = class_exists(Config::class, false) && Config::bool('APP_DEBUG', false);
        $path = explode('?', (string)($_SERVER['REQUEST_URI'] ?? '/'), 2)[0];
        $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $json = str_contains($accept, '/json') || str_contains($accept, '+json');

        if (!headers_sent()) {
            header_remove('Location');
            header_remove('Content-Length');
            header_remove('Content-Encoding');
            http_response_code(500);
            header('Content-Type: ' . ($json ? 'application/json' : 'text/html') . '; charset=UTF-8');
            header('Cache-Control: no-store, private');
            header('X-Request-Id: ' . $requestId);
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        }
        if ($method === 'HEAD') {
            return;
        }

        try {
            if ($json) {
                $payload = [
                    'ok' => false,
                    'error' => ['code' => 'server_error', 'message' => 'Internal Server Error'],
                    'request_id' => $requestId,
                ];
                if ($debug) {
                    $payload['error']['debug'] = $error->getMessage();
                }
                echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);

                return;
            }
            // Прямое подключение работает даже при отсутствии vendor/autoload.php.
            require_once __DIR__ . '/ExceptionPage.php';
            echo ExceptionPage::render($error, $debug, $requestId, $path, $method, $this->root);
        } catch (Throwable $failure) {
            error_log('MyDoMo: error page failed: ' . $failure);
            echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>500 — MyDoMo</title>'
                . '<h1>500 — Внутренняя ошибка</h1><p>Повторите попытку позже.</p><p>Код запроса: '
                . htmlspecialchars($requestId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p></html>';
        }
    }
}
