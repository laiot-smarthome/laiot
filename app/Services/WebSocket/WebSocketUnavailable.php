<?php

declare(strict_types=1);

namespace LaIOT\Services\WebSocket;

use RuntimeException;

/**
 * WebSocket недоступен: выключен или не применены миграции.
 *
 * HTTP-эндпоинт превращает это в 503, а не в 500: панель остаётся рабочей,
 * просто без обновлений.
 *
 * 2026-10-29 — отдельная ошибка доступности websocket.
 */
final class WebSocketUnavailable extends RuntimeException
{
}
