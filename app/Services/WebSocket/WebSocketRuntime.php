<?php

declare(strict_types=1);

namespace LaIOT\Services\WebSocket;

use LaIOT\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Timer;
use Workerman\Worker;

/**
 * Обработчик WebSocket-подключений одного процесса Workerman.
 *
 * Протокол строго исходящий: данные от клиента не принимаются. Подписка
 * приходит в подписанном тикете вместе с рукопожатием, тикет выдаёт
 * HTTP-эндпоинт после проверки сессии. Изменения свойств читаются из outbox
 * websocket_events и адресуются каналу «Объект.свойство»; системные события
 * идут отдельным типом в общий канал system.
 *
 * 2026-10-29 — имена свойств, системные события, пакеты и проверка доступа.
 */
final class WebSocketRuntime
{
    /** @var array<int,array{connection:TcpConnection,user_id:int,username:string,ticket_hash:string,channels:list<string>,since_id:int,expires_at:int}> */
    private array $connections = [];

    /** @var array<string,array<int,true>> */
    private array $channelIndex = [];

    /** @var array<int,list<string>> */
    private array $connectionChannels = [];

    private int $cursor = 0;

    private int $lastErrorAt = 0;

    public function __construct(
        private readonly WebSocketConfig $config,
        private readonly WebSocketTicketService $tickets,
        private readonly WebSocketTicketRepository $ticketRows,
        private readonly WebSocketEventRepository $events,
        private readonly DatabaseManager $database,
        private readonly LoggerInterface $logger
    ) {
    }

    public function boot(Worker $worker): void
    {
        if (!$this->database->schema()->hasTable('websocket_events')
            || !$this->database->schema()->hasTable('websocket_tickets')) {
            throw new \RuntimeException('WebSocket: примените миграции websocket_events и websocket_tickets.');
        }
        $this->cursor = $this->events->maxId();

        // Браузер присылает протокол и тикет в Sec-WebSocket-Protocol.
        // Сервер обязан выбрать ровно один протокол, иначе рукопожатие
        // отклоняется браузером.
        $worker->onWebSocketConnect = function (TcpConnection $connection, Request $request): void {
            try {
                $header = $request->header('sec-websocket-protocol', '');
                if (is_string($header) && str_contains($header, WebSocketChannels::PROTOCOL)) {
                    $connection->headers[] = 'Sec-WebSocket-Protocol: ' . WebSocketChannels::PROTOCOL;
                }
            } catch (Throwable $error) {
                $this->report('WebSocket: ошибка рукопожатия', $error);
            }
        };
        $worker->onWebSocketConnected = function (TcpConnection $connection, Request $request): void {
            try {
                $this->connected($connection, $request);
            } catch (Throwable $error) {
                // Исключение из этого колбэка Workerman трактует как аварию
                // всего процесса; одно плохое подключение не должно ронять сервер.
                $this->report('WebSocket: ошибка подключения', $error);
                try {
                    $this->closeWithReason($connection, 1011, 'Internal error');
                } catch (Throwable) {
                    // Соединение уже могло быть закрыто.
                }
            }
        };
        $worker->onMessage = function (TcpConnection $connection): void {
            $this->incoming($connection);
        };
        $worker->onClose = function (TcpConnection $connection): void {
            $this->remove($connection->id);
        };
        $worker->onWorkerStop = static function (): void {
            Timer::delAll();
        };

        Timer::add(max(20, $this->config->pollMs) / 1000, [$this, 'poll']);
        Timer::add($this->config->heartbeat, [$this, 'heartbeat']);
        Timer::add($this->config->authCheck, [$this, 'authCheck']);
        Timer::add($this->config->pruneInterval, [$this, 'prune']);
    }

    /**
     * Периодическое чтение outbox. За один тик разбирается несколько порций,
     * чтобы догнать накопившиеся изменения без задержки в один интервал.
     */
    public function poll(): void
    {
        for ($round = 0; $round < 5; $round++) {
            try {
                $rows = $this->events->after($this->cursor, $this->config->batch);
            } catch (Throwable $error) {
                $this->databaseFailure('WebSocket: не удалось прочитать outbox', $error);

                return;
            }
            if ($rows === []) {
                return;
            }
            $lastId = $rows[count($rows) - 1]['id'];
            try {
                $this->dispatch($rows);
            } catch (Throwable $error) {
                $this->report('WebSocket: ошибка рассылки', $error);

                return;
            }
            // Курсор двигается только после успешной рассылки: при сбое
            // следующий проход повторит пакет, а клиент отбросит дубли по id.
            $this->cursor = $lastId;
            if (count($rows) < $this->config->batch) {
                return;
            }
        }
    }

    public function heartbeat(): void
    {
        foreach ($this->connections as $id => $state) {
            try {
                if (!$this->ping($state['connection'])) {
                    $this->remove($id);
                }
            } catch (Throwable $error) {
                $this->remove($id);
                $this->report('WebSocket: ошибка heartbeat', $error);
            }
        }
    }

    /**
     * Тикеты и пользователи перепроверяются редко: отзыв при выходе,
     * удаление пользователя и смена пароля закрывают соединение в пределах
     * auth_check секунд.
     */
    public function authCheck(): void
    {
        if ($this->connections === []) {
            return;
        }
        $hashes = [];
        foreach ($this->connections as $state) {
            $hashes[] = $state['ticket_hash'];
        }

        try {
            $valid = $this->ticketRows->validHashes($hashes);
        } catch (Throwable $error) {
            $this->databaseFailure('WebSocket: не удалось проверить тикеты', $error);

            return;
        }

        foreach ($this->connections as $id => $state) {
            if ($state['expires_at'] > 0 && $state['expires_at'] <= time()) {
                try {
                    $this->closeWithReason($state['connection'], 4001, 'Ticket expired');
                } catch (Throwable) {
                    // Соединение уже закрыто.
                }
                $this->remove($id);
                continue;
            }
            if (($valid[$state['ticket_hash']] ?? 0) !== $state['user_id']) {
                try {
                    $this->closeWithReason($state['connection'], 4401, 'Authorization revoked');
                } catch (Throwable) {
                    // Соединение уже закрыто — важен только remove ниже.
                }
                $this->remove($id);
            }
        }
    }

    public function prune(): void
    {
        try {
            $events = $this->events->pruneBefore(
                date('Y-m-d H:i:s', time() - $this->config->retentionHours * 3600)
            );
            // Тикеты живут дольше любого разумного подключения; давно
            // истёкшие строки не нужны даже для аудита.
            $tickets = $this->ticketRows->pruneBefore(date('Y-m-d H:i:s', time() - 7 * 86400));
            if ($events + $tickets > 0) {
                $this->logger->info('WebSocket: очистка выполнена', [
                    'events' => $events,
                    'tickets' => $tickets,
                ]);
            }
        } catch (Throwable $error) {
            $this->databaseFailure('WebSocket: очистка не выполнена', $error);
        }
    }

    private function connected(TcpConnection $connection, Request $request): void
    {
        if (rtrim($request->path(), '/') !== rtrim($this->config->path, '/')) {
            $this->reject($connection, 4404, 'not_found', 'Неизвестный путь WebSocket.');

            return;
        }
        $origin = $request->header('origin', '');
        if (!$this->config->originAllowed(is_string($origin) ? $origin : '', (string)($request->host(true) ?? ''))) {
            $this->reject($connection, 4403, 'origin_denied', 'Источник подключения не разрешён.');

            return;
        }
        if (count($this->connections) >= $this->config->maxConnections) {
            $this->reject($connection, 4429, 'too_many_connections', 'Слишком много подключений.');

            return;
        }

        $ticket = $this->ticket($request);
        if ($ticket === '') {
            $this->reject($connection, 4401, 'unauthorized', 'Не передан тикет подключения.');

            return;
        }
        $authorized = $this->tickets->authorize($ticket);
        if ($authorized === null) {
            $this->reject($connection, 4401, 'unauthorized', 'Тикет недействителен или истёк.');

            return;
        }

        $id = $connection->id;
        $connection->maxSendBufferSize = 4 * 1024 * 1024;
        $connection->maxPackageSize = 16384;
        $connection->onBufferFull = function (TcpConnection $slow): void {
            // Медленный клиент не должен копить пакеты в памяти процесса.
            // Закрываем без кадра причины: буфер уже переполнен.
            $slow->close();
            $this->remove($slow->id);
        };
        $connection->onError = function (TcpConnection $failed): void {
            $this->remove($failed->id);
        };

        $this->connections[$id] = [
            'connection' => $connection,
            'user_id' => $authorized['user_id'],
            'username' => $authorized['username'],
            'ticket_hash' => hash('sha256', $ticket),
            'channels' => $authorized['channels'],
            'since_id' => $authorized['since_id'],
            'expires_at' => $authorized['expires_at'],
        ];
        foreach ($authorized['channels'] as $channel) {
            $this->channelIndex[$channel][$id] = true;
        }
        $this->connectionChannels[$id] = $authorized['channels'];

        if (!$this->send($connection, [
            'v' => 1,
            'type' => 'ready',
            'user' => ['id' => $authorized['user_id'], 'name' => $authorized['name']],
            'channels' => count($authorized['channels']),
            'system' => true,
            'cursor' => $authorized['since_id'],
            'server_time' => date(DATE_ATOM),
        ])) {
            $this->remove($id);

            return;
        }

        $this->replay($connection, $authorized['channels'], $authorized['since_id']);
        $this->logger->debug('WebSocket: подключение открыто', [
            'id' => $id,
            'user_id' => $authorized['user_id'],
            'channels' => count($authorized['channels']),
        ]);
    }

    /**
     * События, случившиеся между снимком в тикете и фактическим подключением.
     *
     * @param list<string> $channels
     */
    private function replay(TcpConnection $connection, array $channels, int $sinceId): void
    {
        $wanted = array_fill_keys($channels, true);
        try {
            $rows = $this->events->latestAfter($sinceId, $this->config->replayLimit);
        } catch (Throwable $error) {
            $this->databaseFailure('WebSocket: не удалось прочитать реплей', $error);

            return;
        }
        $latest = [];
        foreach ($rows as $row) {
            if ($row['type'] === 'system') {
                if (isset($wanted[WebSocketChannels::SYSTEM])) {
                    $this->sendSystem($connection, $row);
                }
                continue;
            }
            $key = WebSocketChannels::key($row['object_title'], $row['property_title']);
            if (isset($wanted[$key])) {
                $latest[$key] = $this->event($row);
            }
        }
        if ($latest !== []) {
            $this->sendChanges($connection, array_values($latest));
        }
    }

    /**
     * @param list<array{id:int,type:string,object_id:int,property_id:int,object_title:string,property_title:string,event_name:string,payload:string,old_value:string,value:string,source:string,created_at:string}> $rows
     */
    private function dispatch(array $rows): void
    {
        if ($this->connections === []) {
            return;
        }

        /** @var array<string,array{0:int,1:string,2:string,3:string}> $latest */
        $latest = [];
        $systemRows = [];
        foreach ($rows as $row) {
            if ($row['type'] === 'system') {
                if (isset($this->channelIndex[WebSocketChannels::SYSTEM])) {
                    $systemRows[] = $row;
                }
                continue;
            }
            $key = WebSocketChannels::key($row['object_title'], $row['property_title']);
            if (isset($this->channelIndex[$key])) {
                $latest[$key] = $this->event($row);
            }
        }

        /** @var array<int,list<array{0:int,1:string,2:string,3:string}>> $buffers */
        $buffers = [];
        foreach ($latest as $key => $event) {
            foreach (array_keys($this->channelIndex[$key]) as $id) {
                if (isset($this->connections[$id])) {
                    $buffers[$id][] = $event;
                }
            }
        }
        foreach ($buffers as $id => $events) {
            if (!isset($this->connections[$id])
                || !$this->sendChanges($this->connections[$id]['connection'], $events)) {
                $this->remove($id);
            }
        }

        foreach ($systemRows as $row) {
            $this->dispatchSystem($row);
        }
    }

    /**
     * @param array{id:int,type:string,object_id:int,property_id:int,object_title:string,property_title:string,event_name:string,payload:string,old_value:string,value:string,source:string,created_at:string} $row
     * @return array{0:int,1:string,2:string,3:string}
     */
    private function event(array $row): array
    {
        return [
            $row['id'],
            WebSocketChannels::key($row['object_title'], $row['property_title']),
            $row['value'],
            $row['created_at'],
        ];
    }

    /**
     * @param array{id:int,type:string,object_id:int,property_id:int,object_title:string,property_title:string,event_name:string,payload:string,old_value:string,value:string,source:string,created_at:string} $row
     */
    private function dispatchSystem(array $row): void
    {
        $data = json_decode($row['payload'], true);
        $payload = [
            'v' => 1,
            'type' => 'system',
            'id' => $row['id'],
            'event' => $row['event_name'],
            'data' => is_array($data) ? $data : [],
            'at' => $row['created_at'],
        ];
        foreach (array_keys($this->channelIndex[WebSocketChannels::SYSTEM] ?? []) as $id) {
            if (isset($this->connections[$id])
                && !$this->send($this->connections[$id]['connection'], $payload)) {
                $this->remove($id);
            }
        }
    }

    /**
     * @param array{id:int,type:string,object_id:int,property_id:int,object_title:string,property_title:string,event_name:string,payload:string,old_value:string,value:string,source:string,created_at:string} $row
     */
    private function sendSystem(TcpConnection $connection, array $row): void
    {
        $data = json_decode($row['payload'], true);
        $this->send($connection, [
            'v' => 1,
            'type' => 'system',
            'id' => $row['id'],
            'event' => $row['event_name'],
            'data' => is_array($data) ? $data : [],
            'at' => $row['created_at'],
        ]);
    }

    /**
     * @param list<array{0:int,1:string,2:string,3:string}> $events
     */
    private function sendChanges(TcpConnection $connection, array $events): bool
    {
        return $this->send($connection, ['v' => 1, 'type' => 'changes', 'events' => $events]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function send(TcpConnection $connection, array $payload): bool
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return false;
        }

        return $connection->send($json) !== false;
    }

    /**
     * Данные от клиента не являются частью протокола: даже подписка приходит
     * тикетом. Такое сообщение закрывает соединение с кодом политики.
     */
    private function incoming(TcpConnection $connection): void
    {
        $this->closeWithReason($connection, 1008, 'Incoming messages are not supported');
        $this->remove($connection->id);
    }

    private function ping(TcpConnection $connection): bool
    {
        $type = $connection->websocketType;
        $connection->websocketType = "\x89";
        try {
            return $connection->send('') !== false;
        } finally {
            $connection->websocketType = $type !== '' ? $type : "\x81";
        }
    }

    private function reject(TcpConnection $connection, int $closeCode, string $code, string $message): void
    {
        $this->send($connection, ['v' => 1, 'type' => 'error', 'code' => $code, 'message' => $message]);
        $this->closeWithReason($connection, $closeCode, $message);
    }

    private function closeWithReason(TcpConnection $connection, int $code, string $reason): void
    {
        $type = $connection->websocketType;
        $connection->websocketType = "\x88";
        try {
            $connection->close(pack('n', $code) . substr($reason, 0, 120));
        } finally {
            $connection->websocketType = $type !== '' ? $type : "\x81";
        }
    }

    private function ticket(Request $request): string
    {
        $header = $request->header('sec-websocket-protocol', '');
        if (!is_string($header) || $header === '') {
            return '';
        }
        foreach (explode(',', $header) as $token) {
            $token = trim($token);
            if (preg_match('/^[a-f0-9]{64}$/D', $token) === 1) {
                return $token;
            }
        }

        return '';
    }

    private function remove(int $id): void
    {
        if (!isset($this->connections[$id])) {
            return;
        }
        foreach ($this->connectionChannels[$id] ?? [] as $channel) {
            unset($this->channelIndex[$channel][$id]);
            if (($this->channelIndex[$channel] ?? []) === []) {
                unset($this->channelIndex[$channel]);
            }
        }
        unset($this->connectionChannels[$id], $this->connections[$id]);
    }

    private function report(string $message, Throwable $error): void
    {
        $now = time();
        if ($now - $this->lastErrorAt < 30) {
            return;
        }
        $this->lastErrorAt = $now;
        $this->logger->error($message, ['error' => $error->getMessage()]);
    }

    /**
     * Сбой БД закрывает соединение, чтобы следующий запрос открыл новое.
     * Без этого после рестарта MariaDB процесс продолжал бы обращаться к
     * мёртвому PDO и не восстанавливался без перезапуска сервиса.
     */
    private function databaseFailure(string $message, Throwable $error): void
    {
        $this->database->disconnect();
        $this->report($message, $error);
    }
}
