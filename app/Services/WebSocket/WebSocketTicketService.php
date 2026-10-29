<?php

declare(strict_types=1);

namespace LaIOT\Services\WebSocket;

use Illuminate\Database\QueryException;
use InvalidArgumentException;
use LaIOT\Services\Auth\UserRepository;
use Throwable;

/**
 * Выдача и проверка билета на WebSocket-подключение.
 *
 * HTTP-сессия уже проверена middleware `auth`; сервис повторно читает
 * администратора, чтобы взять актуальный отпечаток пароля. В ответ входит
 * снимок значений: страница показывает его сразу, а сервер затем присылает
 * только изменения после курсора снимка.
 *
 * 2026-10-29 — тикет, снимок и курсор выдаются одной операцией.
 */
final readonly class WebSocketTicketService
{
    public function __construct(
        private WebSocketConfig $config,
        private WebSocketTicketRepository $tickets,
        private WebSocketEventRepository $events,
        private UserRepository $users
    ) {
    }

    /**
     * @param array{id:int,username:string,name:string,is_admin?:bool,stamp?:string} $sessionUser
     * @param array<int, mixed> $channels
     * @return array{
     *     ticket:string,
     *     expires_in:int,
     *     cursor:int,
     *     values:list<array{object:string,property:string,value:string}>,
     *     url:string,
     *     path:string,
     *     protocol:string
     * }
     */
    public function issue(array $sessionUser, array $channels): array
    {
        if (!$this->config->enabled) {
            throw new WebSocketUnavailable('WebSocket выключен в настройках.');
        }
        $user = $this->users->sessionUser((int)$sessionUser['id']);
        if ($user === null) {
            throw new WebSocketUnauthorized('Требуется действующая сессия администратора.');
        }
        $normalized = WebSocketChannels::normalize($channels, $this->config->maxChannels);

        try {
            $cursor = $this->events->maxId();
            $values = $this->events->currentValues($normalized);
            $ticket = $this->tickets->issue($user['id'], $user['stamp'], $normalized, $cursor, $this->config->ticketTtl);
        } catch (QueryException $error) {
            throw new WebSocketUnavailable('Таблицы websocket не найдены. Примените миграции.', 0, $error);
        } catch (Throwable $error) {
            if ($error instanceof WebSocketUnavailable || $error instanceof InvalidArgumentException) {
                throw $error;
            }
            throw new WebSocketUnavailable('Не удалось подготовить WebSocket-подключение.', 0, $error);
        }

        return [
            'ticket' => $ticket,
            'expires_in' => $this->config->ticketTtl,
            'cursor' => $cursor,
            'values' => $values,
            'url' => $this->config->publicUrl(),
            'path' => $this->config->path,
            'protocol' => WebSocketChannels::PROTOCOL,
        ];
    }

    /**
     * @return array{user_id:int,username:string,name:string,channels:list<string>,since_id:int,expires_at:int}|null
     */
    public function authorize(string $ticket): ?array
    {
        if (!$this->config->enabled) {
            return null;
        }

        try {
            return $this->tickets->resolve($ticket, $this->config->maxChannels);
        } catch (Throwable) {
            return null;
        }
    }
}
