<?php

declare(strict_types=1);

namespace LaIOT\Services\WebSocket;

use RuntimeException;

/**
 * Сессия перестала быть действующей между middleware `auth` и выдачей тикета.
 *
 * 2026-10-29 — отдельная ошибка авторизации websocket.
 */
final class WebSocketUnauthorized extends RuntimeException
{
}
