<?php

declare(strict_types=1);

namespace LaIOT\Mqtt;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Издатель команд MQTT.
 *
 * В процессе приёма используется общее соединение с подпиской: отправка не
 * открывает второе TCP-соединение и не ждёт следующего пакета. В отдельном
 * процессе очереди (core-worker module.mqtt) соединение открывается лениво
 * и переиспользуется, пока не простояло дольше keepalive: закрытый брокером
 * сокет иначе «принимает» первую команду в никуда.
 *
 * 2026-10-27 — общий и ленивый режимы издателя для быстрого пути MQTT.
 */
final class Publisher implements PublisherInterface
{
    private bool $shared = false;

    private float $lastUsed = 0.0;

    public function __construct(
        private readonly Settings $settings,
        private readonly Broker $broker,
        private readonly RuntimeState $runtime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Режим общего соединения включает цикл приёма в этом же процессе.
     */
    public function useSharedBroker(bool $shared): void
    {
        $this->shared = $shared;
    }

    public function publish(string $topic, string $message, int $qos = 0, bool $retain = false): void
    {
        $topic = trim($topic);
        if ($topic === '') {
            throw new RuntimeException('Не указан MQTT-топик для отправки.');
        }

        $started = microtime(true);
        if ($this->shared) {
            if (!$this->broker->isConnected()) {
                throw new RuntimeException('Соединение приёма MQTT не установлено.');
            }
            $this->broker->publish($topic, $message, $qos, $retain);
            $this->runtime->published((microtime(true) - $started) * 1000);

            return;
        }

        $this->connectIfNeeded();
        try {
            $this->broker->tick();
            $this->broker->publish($topic, $message, $qos, $retain);
        } catch (Throwable $error) {
            $this->logger->warning('MQTT: соединение издателя потеряно, переподключаемся', [
                'error' => $error->getMessage(),
            ]);
            $this->broker->close();
            $this->connect();
            $this->broker->publish($topic, $message, $qos, $retain);
        }

        $this->lastUsed = microtime(true);
        $this->runtime->published((microtime(true) - $started) * 1000);
    }

    private function connectIfNeeded(): void
    {
        $keepAlive = max(5, $this->settings->int('keepalive'));
        if (!$this->broker->isConnected()) {
            $this->connect();

            return;
        }
        if ($this->lastUsed > 0 && microtime(true) - $this->lastUsed > $keepAlive - 2) {
            // Брокер закрывает простаивающее соединение; новое дешевле, чем
            // попытка записи в мёртвый сокет.
            $this->broker->close();
            $this->connect();
        }
    }

    private function connect(): void
    {
        try {
            $this->broker->connect();
        } catch (Throwable $error) {
            $this->runtime->error('MQTT publish connect: ' . $error->getMessage());
            throw new RuntimeException('Не удалось подключиться к MQTT-брокеру: ' . $error->getMessage(), 0, $error);
        }
    }
}
