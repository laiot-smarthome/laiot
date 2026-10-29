<?php

declare(strict_types=1);

namespace LaIOT\Services\WebSocket;

use LaIOT\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Запись событий в WebSocket-outbox.
 *
 * Свойство вызывается из PropertyValueService внутри той же транзакции, что
 * и значение: событие не появится без значения и не потеряется при откате.
 * Системное событие пишет наблюдатель SystemEvents. Ошибка публикации не
 * должна отменять основную операцию.
 *
 * 2026-10-29 — outbox свойств и системных событий, мягкая деградация.
 */
final class WebSocketPublisher
{
    private ?bool $available = null;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly WebSocketConfig $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function publish(
        int $objectId,
        int $propertyId,
        string $objectTitle,
        string $propertyTitle,
        string $oldValue,
        string $newValue,
        string $source,
        string $occurredAt
    ): void {
        if (!$this->config->enabled || $oldValue === $newValue || $objectId < 1 || $propertyId < 1) {
            return;
        }
        if (!$this->available()) {
            return;
        }

        try {
            $this->database->connection()->table('websocket_events')->insert([
                'type' => 'property',
                'object_id' => $objectId,
                'property_id' => $propertyId,
                'object_title' => mb_substr($objectTitle, 0, 191),
                'property_title' => mb_substr($propertyTitle, 0, 191),
                'event_name' => '',
                'payload' => null,
                'old_value' => $oldValue,
                'value' => $newValue,
                'source' => mb_substr(trim($source), 0, 191),
                'created_at' => $occurredAt,
            ]);
        } catch (Throwable $error) {
            $this->degrade($error, ['object' => $objectTitle, 'property' => $propertyTitle]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function publishSystem(string $event, array $data): void
    {
        if (!$this->config->enabled || preg_match('/^(?:system|module\.[a-z][a-z0-9_]{1,63})\.[a-z][a-z0-9_.-]{0,180}$/D', $event) !== 1) {
            return;
        }
        if (!$this->available()) {
            return;
        }
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($payload === false) {
            return;
        }

        try {
            $this->database->connection()->table('websocket_events')->insert([
                'type' => 'system',
                'object_id' => null,
                'property_id' => null,
                'object_title' => '',
                'property_title' => '',
                'event_name' => mb_substr($event, 0, 191),
                'payload' => $payload,
                'old_value' => null,
                'value' => null,
                'source' => 'system',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $error) {
            $this->degrade($error, ['event' => $event]);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function degrade(Throwable $error, array $context): void
    {
        $this->available = false;
        $this->logger->warning('WebSocket: событие не записано, канал отключён в этом процессе', $context + [
            'error' => $error->getMessage(),
        ]);
    }

    /**
     * Таблица проверяется один раз на процесс: PropertyValueService вызывает
     * публикацию на каждой записи, а schema-запрос на каждую запись дорог.
     */
    private function available(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        try {
            return $this->available = $this->database->schema()->hasTable('websocket_events');
        } catch (Throwable) {
            return $this->available = false;
        }
    }
}
