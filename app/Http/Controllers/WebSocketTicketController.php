<?php

declare(strict_types=1);

namespace LaIOT\Http\Controllers;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LaIOT\Http\Controller;
use LaIOT\Http\Session\SessionStore;
use LaIOT\Services\WebSocket\WebSocketConfig;
use LaIOT\Services\WebSocket\WebSocketTicketService;
use LaIOT\Services\WebSocket\WebSocketUnauthorized;
use LaIOT\Services\WebSocket\WebSocketUnavailable;
use Symfony\Component\HttpFoundation\Response;

/**
 * Выдача короткоживущего тикета на WebSocket-подключение.
 *
 * Это виджет оболочки, поэтому отдельный JSON-маршрут допустим. Тикет
 * привязан к сессии администратора и содержит только переданные страницей
 * каналы; сам сокет остаётся строго исходящим.
 *
 * 2026-10-29 — эндпоинт тикета, снимка и курсора.
 */
final readonly class WebSocketTicketController extends Controller
{
    public function __construct(
        private SessionStore $session,
        private ValidationFactory $validation,
        private WebSocketConfig $config,
        private WebSocketTicketService $tickets
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->config->enabled) {
            return $this->failure(503, 'websocket_disabled', 'WebSocket выключен в настройках.');
        }
        $user = $this->session->user();
        if ($user === null) {
            return $this->failure(401, 'authentication_required', 'Необходимо войти в систему.');
        }

        $validator = $this->validation->make($request->all(), [
            'channels' => ['required', 'array', 'min:1', 'max:' . $this->config->maxChannels],
            'channels.*' => ['string', 'regex:/^(?:system|[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*)$/'],
        ], [
            'channels.required' => 'Передайте свойства, которые видны на странице.',
            'channels.array' => 'Список свойств имеет неверный формат.',
            'channels.min' => 'Нужно хотя бы одно свойство.',
            'channels.max' => 'Слишком много свойств для одного подключения.',
            'channels.*.string' => 'Свойство должно быть строкой «Объект.свойство».',
            'channels.*.regex' => 'Свойство должно иметь вид «Объект.свойство», например Light.status.',
        ], ['channels' => 'Список свойств', 'channels.*' => 'Свойство']);
        if ($validator->fails()) {
            return $this->json([
                'ok' => false,
                'error' => [
                    'code' => 'validation_failed',
                    'message' => 'Проверьте список свойств.',
                    'fields' => $validator->errors()->toArray(),
                ],
            ], 422, ['Cache-Control' => 'no-store']);
        }

        /** @var array<int, mixed> $channels */
        $channels = $validator->validated()['channels'];
        try {
            $ticket = $this->tickets->issue($user, $channels);
        } catch (WebSocketUnavailable $error) {
            return $this->failure(503, 'websocket_unavailable', $error->getMessage());
        } catch (WebSocketUnauthorized $error) {
            return $this->failure(401, 'authentication_required', $error->getMessage());
        } catch (InvalidArgumentException $error) {
            return $this->failure(422, 'invalid_channels', $error->getMessage());
        }

        return $this->json(['ok' => true, 'data' => $ticket], 200, ['Cache-Control' => 'no-store']);
    }

    private function failure(int $status, string $code, string $message): Response
    {
        return $this->json([
            'ok' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], $status, ['Cache-Control' => 'no-store']);
    }
}
