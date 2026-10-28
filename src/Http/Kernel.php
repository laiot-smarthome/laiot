<?php

declare(strict_types=1);

namespace MajorDoMo\Http;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use MajorDoMo\Logging\ErrorRepository;
use MajorDoMo\Logging\RequestContext;
use MajorDoMo\Support\MaintenanceMode;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * HTTP-граница MyDoMo: диспетчеризация, request id и единые 404/405/500.
 *
 * Пока это минимальный kernel без глобального middleware-стека. Он уже
 * отделяет инфраструктурные ошибки от ответов контроллеров и не отдаёт
 * stack trace в production.
 *
 * Изменения:
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
        private ?ViewFactory $view = null
    ) {
    }

    public function handle(Request $request): Response
    {
        $requestId = $this->requestId($request);
        $request->attributes->set('request_id', $requestId);
        $this->context->setRequestId($requestId);

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

    private function allowedDuringMaintenance(Request $request): bool
    {
        return in_array($request->getPathInfo(), [
            '/login', '/logout', '/health', '/api/v1/health',
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
