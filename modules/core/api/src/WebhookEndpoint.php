<?php

declare(strict_types=1);

namespace MajorDoMo\Modules\Core\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Runtime\CodeRunner;
use MajorDoMo\Scripts\ScriptRepository;
use MajorDoMo\Scripts\ScriptRunner;
use Throwable;

/**
 * Публичный вход webhook: /api/v1/webhooks/{secret}.
 *
 * Без сессии и API-токена: аутентификация — сам секрет. Метод проверяется по
 * настройке входа. Ошибки обработчика не раскрываются наружу.
 */
final readonly class WebhookEndpoint
{
    public function __construct(
        private ModuleManager $modules,
        private ScriptRepository $scripts,
        private ScriptRunner $scriptRunner,
        private CodeRunner $runner
    ) {
    }

    public function __invoke(Request $request, string $secret): JsonResponse
    {
        $db = $this->modules->db('api');
        if ($db === null) {
            return $this->error(500, 'not_configured', 'Webhook-модуль не настроен.');
        }
        $service = new WebhookService(new WebhookRepository($db), $this->scripts, $this->scriptRunner, $this->runner);

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
            $headers[$name] = is_array($values) ? implode(', ', array_map('strval', $values)) : (string)$values;
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
            error_log('MyDoMo webhook endpoint failed: ' . $error->getMessage());

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
