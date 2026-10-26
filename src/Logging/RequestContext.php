<?php

declare(strict_types=1);

namespace MajorDoMo\Logging;

/**
 * Контекст текущего запроса для логов.
 *
 * Kernel выставляет request_id до диспетчеризации; processor добавляет его
 * в каждую запись, поэтому один и тот же id есть в HTTP-ответе, ошибке и
 * записях модулей.
 */
final class RequestContext
{
    private string $requestId = '';

    public function setRequestId(string $requestId): void
    {
        $this->requestId = $requestId;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }
}
