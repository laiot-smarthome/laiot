<?php

declare(strict_types=1);

namespace MajorDoMo\Http;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Единый ответ ошибки для HTTP-границы.
 *
 * JSON нужен API/htmx/частичным запросам, HTML — прямому переходу браузером,
 * обычный текст остаётся аварийным запасным вариантом для middleware и
 * случая, когда Blade тоже недоступен. Диагностика исключения в debug
 * рендерится независимо от Blade, аргументы вызовов не показываются.
 *
 * Изменения:
 * 2026-10-29 — автономная debug-страница со стеком и HTML-резерв для 500.
 * 2026-10-28 — браузер получает страницу errors.page вместо простого текста.
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
        ?Throwable $error = null,
        ?ViewFactory $view = null
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

            return new JsonResponse($payload, $status, ['Cache-Control' => 'no-store, private']);
        }

        $debugError = $debug ? $error : null;

        if ($debugError !== null) {
            return new Response(
                ExceptionPage::render($debugError, true, $requestId, $request->getPathInfo(), $request->getMethod()),
                $status,
                ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store, private']
            );
        }

        if ($view !== null) {
            try {
                $html = $view
                    ->make('errors.page', ErrorPage::data($request, $status, $code, $requestId, $debugError))
                    ->render();

                return new Response($html, $status, [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'Cache-Control' => 'no-store, private',
                ]);
            } catch (Throwable) {
                // Шаблон может упасть по той же причине, что и запрос
                // (сломанный кэш Blade, недоступный каталог). Исходный код
                // ответа важнее: отдаём минимальный текст с request id.
            }
        }

        if ($status === 500 && $error !== null) {
            return new Response(
                ExceptionPage::render($error, false, $requestId, $request->getPathInfo(), $request->getMethod()),
                $status,
                ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store, private']
            );
        }

        $text = $message . "\nrequest_id: " . $requestId;
        return new Response($text, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
