<?php

declare(strict_types=1);

namespace MajorDoMo\Logging;

use Monolog\LogRecord;

/**
 * Добавляет request_id в extra каждой записи Monolog.
 */
final readonly class RequestIdProcessor
{
    public function __construct(private RequestContext $context)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $requestId = $this->context->requestId();
        if ($requestId === '') {
            return $record;
        }

        return $record->with(extra: ['request_id' => $requestId] + $record->extra);
    }
}
