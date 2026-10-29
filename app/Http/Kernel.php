<?php

declare(strict_types=1);

namespace LaIOT\Http;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use LaIOT\Services\Install\InstallService;
use LaIOT\Services\Logging\ErrorRepository;
use LaIOT\Services\Logging\RequestContext;
use LaIOT\Support\MaintenanceMode;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * HTTP-граница LaIOT: диспетчеризация, request id и единые 404/405/500.
 *
 * Пока это минимальный kernel без глобального middleware-стека. Он уже
 * отделяет инфраструктурные ошибки от ответов контроллеров и не отдаёт
 * stack trace в production.
 *
 * Изменения:
 * 2026-10-29 — HTTP-исключения авторизации компонентов сохраняют статус, включая 403.
 * 2026-10-29 — некорректная структура запроса возвращает 400, а не ошибку ядра.
 * 2026-10-28 — до установки браузерные страницы уводятся на `/install`,
 *              API отвечает 503 JSON; установленная система работает как раньше.
 * 2026-10-28 — браузерные 404/405/500/503 отдают HTML-страницу с маскотом.
 * 2026-10-26 — обслуживание разрешает защищённые маршруты управления и выход.
 */
final readonly class Kernel
{
    public function __construct(
        private Router $router,
        private LoggerInterface $logger,
        private bool $debug = false,
        private RequestContext $context = new RequestContext(),
        private ?ErrorRepository $errors = null,
        private ?MaintenanceMode $maintenance = null,
        private ?ViewFactory $view = null,
        private ?InstallService $installer = null,
        private bool $installGuard = false
    ) {
    }

    public function handle(Request $request): Response
    {
        $requestId = $this->requestId($request);
        $request->attributes->set('request_id', $requestId);
        $this->context->setRequestId($requestId);

        // Пока LaIOT не установлена, любая страница ведёт в мастер. Это
        // дешевле и понятнее, чем вход в несуществующую панель; `/install`,
        // health и статика исключены, API получает JSON 503.
        if ($this->installGuard && $this->installer !== null
            && !$this->installExempt($request) && !$this->installer->installed()) {
            return $this->installResponse($request, $requestId);
        }

        if ($this->maintenance?->enabled() === true && !$this->allowedDuringMaintenance($request)) {
            $response = $this->error($request, 503, 'maintenance', 'Service Unavailable', $requestId);
            SecurityHeaders::apply($response);
            $response->headers->set('Retry-After', '60');
            $response->headers->set('X-Request-Id', $requestId);

            return $response;
        }

        try {
            $response = $this->router->dispatch($request);
        } catch (NotFoundHttpException) {
            $response = $this->error($request, 404, 'not_found', 'Not Found', $requestId);
        } catch (MethodNotAllowedHttpException $error) {
            $response = $this->error($request, 405, 'method_not_allowed', 'Method Not Allowed', $requestId);
            $response->headers->add($error->getHeaders());
        } catch (HttpExceptionInterface $error) {
            $status = $error->getStatusCode();
            $response = $this->error($request, $status, 'http_error', Response::$statusTexts[$status] ?? 'HTTP error', $requestId);
            $response->headers->add($error->getHeaders());
        } catch (RequestExceptionInterface) {
            $response = $this->error($request, 400, 'bad_request', 'Bad Request', $requestId);
        } catch (Throwable $error) {
            $this->logger->error('Unhandled request error', [
                'exception' => $error,
                'request_id' => $requestId,
                'path' => $request->getPathInfo(),
            ]);
            $this->errors?->record('error', $error->getMessage(), [
                'class' => $error::class,
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'path' => $request->getPathInfo(),
                'exception' => $error,
            ], $requestId);
            $response = $this->error(
                $request,
                500,
                'server_error',
                'Internal Server Error',
                $requestId,
                $error
            );
        }

        SecurityHeaders::apply($response);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function error(
        Request $request,
        int $status,
        string $code,
        string $message,
        string $requestId,
        ?Throwable $error = null
    ): Response {
        return ErrorResponse::make(
            $request,
            $status,
            $code,
            $message,
            $requestId,
            $this->debug,
            $error,
            $this->view
        );
    }

    private function installExempt(Request $request): bool
    {
        $path = $request->getPathInfo();

        return $path === '/install'
            || $path === '/health'
            || $path === '/api/v1/health'
            || $path === '/favicon.ico'
            || str_starts_with($path, '/assets/');
    }

    private function installResponse(Request $request, string $requestId): Response
    {
        $path = $request->getPathInfo();
        if ($request->expectsJson() || str_starts_with($path, '/api/')) {
            $response = new JsonResponse([
                'ok' => false,
                'error' => [
                    'code' => 'not_installed',
                    'message' => 'LaIOT ещё не установлена. Откройте /install.',
                ],
                'request_id' => $requestId,
            ], 503);
        } else {
            $response = new RedirectResponse('/install');
        }

        SecurityHeaders::apply($response);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function allowedDuringMaintenance(Request $request): bool
    {
        return in_array($request->getPathInfo(), [
            '/login', '/logout', '/health', '/api/v1/health', '/install',
            '/admin/maintenance', '/admin/maintenance/enable', '/admin/maintenance/disable',
        ], true);
    }

    private function requestId(Request $request): string
    {
        $provided = trim((string)$request->headers->get('X-Request-Id', ''));
        if ($provided !== '' && preg_match('/^[A-Za-z0-9._-]{1,64}$/D', $provided) === 1) {
            return $provided;
        }

        return bin2hex(random_bytes(8));
    }
}
