<?php

declare(strict_types=1);

namespace MajorDoMo\Api\Webhooks;

use RuntimeException;

/**
 * Ошибка входящего webhook с HTTP-статусом и Allow-заголовком.
 */
final class WebhookException extends RuntimeException
{
    /**
     * @param list<string> $allow
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $allow = []
    ) {
        parent::__construct($message);
    }
}
