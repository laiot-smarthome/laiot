<?php

declare(strict_types=1);

namespace MajorDoMo\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Единый ответ ошибки для HTTP-границы.
 *
 * JSON нужен API/htmx/частичным запросам, обычный текст — для прямого
 * перехода браузером. Stack trace не отдаётся даже в debug: только текст
 * исключения, чтобы не утекали пути и параметры.
 */
final class ErrorResponse
{
    public static function make(
        Request $request,
        int $status,
        string $code,
        string $message,
        string $requestId,
        bool $debug = false,
        ?Throwable $error = null
    ): Response {
        if ($request->expectsJson()) {
            $payload = [
                'ok' => false,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
                'request_id' => $requestId,
            ];
            if ($debug && $error !== null) {
                $payload['error']['debug'] = $error->getMessage();
            }

            return new JsonResponse($payload, $status);
        }

        $text = $message . "\nrequest_id: " . $requestId;
        if ($debug && $error !== null) {
            $text .= "\n" . $error->getMessage();
        }

        return new Response($text, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
