<?php

declare(strict_types=1);

namespace LaIOT\Services\Webhooks;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Публичный вход webhook: /api/v1/webhooks/{secret}.
 *
 * Без сессии и API-токена: аутентификация — сам секрет. Метод проверяется по
 * настройке входа. Ошибки обработчика не раскрываются наружу.
 */
final readonly class WebhookEndpoint
{
    public function __construct(private WebhookService $webhooks)
    {
    }

    public function __invoke(Request $request, string $secret): JsonResponse
    {
        $service = $this->webhooks;

        $payload = [];
        $content = (string)$request->getContent();
        if ($content !== '') {
            $decoded = json_decode($content, true);
            $payload = is_array($decoded) ? $decoded : ['raw' => $content];
        } elseif ($request->request->count() > 0) {
            $payload = $request->request->all();
        }

        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = implode(', ', array_map('strval', $values));
        }

        try {
            $result = $service->handle($secret, strtoupper($request->getMethod()), $payload, $headers);

            return new JsonResponse(['ok' => true, 'data' => $result]);
        } catch (WebhookException $error) {
            $response = $this->error($error->status, $error->errorCode, $error->getMessage());
            if ($error->allow !== []) {
                $response->headers->set('Allow', implode(', ', $error->allow));
            }

            return $response;
        } catch (Throwable $error) {
            error_log('LaIOT webhook endpoint failed: ' . $error->getMessage());

            return $this->error(500, 'server_error', 'Внутренняя ошибка webhook.');
        }
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse([
            'ok' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
