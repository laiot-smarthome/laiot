<?php

declare(strict_types=1);

namespace MajorDoMo\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use MajorDoMo\Logging\RequestContext;
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
 */
final readonly class Kernel
{
    public function __construct(
        private Router $router,
        private LoggerInterface $logger,
        private bool $debug = false,
        private RequestContext $context = new RequestContext()
    ) {
    }

    public function handle(Request $request): Response
    {
        $requestId = $this->requestId($request);
        $request->attributes->set('request_id', $requestId);
        $this->context->setRequestId($requestId);

        try {
            $response = $this->router->dispatch($request);
        } catch (NotFoundHttpException) {
            $response = ErrorResponse::make($request, 404, 'not_found', 'Not Found', $requestId);
        } catch (MethodNotAllowedHttpException $error) {
            $response = ErrorResponse::make($request, 405, 'method_not_allowed', 'Method Not Allowed', $requestId);
            $response->headers->add($error->getHeaders());
        } catch (Throwable $error) {
            $this->logger->error('Unhandled request error', [
                'exception' => $error,
                'request_id' => $requestId,
                'path' => $request->getPathInfo(),
            ]);
            $response = ErrorResponse::make(
                $request,
                500,
                'server_error',
                'Internal Server Error',
                $requestId,
                $this->debug,
                $error
            );
        }

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
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
