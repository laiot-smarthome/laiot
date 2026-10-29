<?php

declare(strict_types=1);

namespace LaIOT\Mqtt;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\MqttClient;

/**
 * MQTT-клиент с ограничением на чтение рукопожатия.
 *
 * php-mqtt переводит сокет в неблокирующий режим, но чтение CONNACK в
 * `performConnectionHandshake()` ждёт первый байт в цикле `fread` без
 * таймаута. Если брокер принял TCP и молчит (фильтрация, перегрузка,
 * неверный порт), процесс крутится на 100% CPU и не доходит до таймаута
 * подключения. Здесь блокирующее чтение заменено на `stream_select` с
 * дедлайном; неблокирующее чтение цикла остаётся прежним.
 *
 * 2026-10-27 — защита от зависания рукопожатия публичного/чужого брокера.
 */
final class SocketClient extends MqttClient
{
    private float $readDeadline = 0.0;

    public function __construct(
        string $host,
        int $port,
        ?string $clientId = null,
        string $protocol = self::MQTT_3_1_1,
        private readonly float $connectTimeout = 5.0
    ) {
        parent::__construct($host, $port, $clientId, $protocol);
    }

    public function connect(?ConnectionSettings $settings = null, bool $useCleanSession = false): void
    {
        $this->readDeadline = microtime(true) + max(1.0, $this->connectTimeout);
        parent::connect($settings, $useCleanSession);
    }

    /**
     * @throws DataTransferException
     */
    protected function readFromSocket(int $limit = self::SOCKET_READ_BUFFER_SIZE, bool $withoutBlocking = false): string
    {
        if ($this->socket === null || !is_resource($this->socket)) {
            throw new DataTransferException(
                DataTransferException::EXCEPTION_RX_DATA,
                'MQTT socket is not connected.'
            );
        }

        if ($withoutBlocking) {
            $chunk = @fread($this->socket, $limit);
            if ($chunk === false) {
                throw new DataTransferException(
                    DataTransferException::EXCEPTION_RX_DATA,
                    'Reading data from the MQTT socket failed.'
                );
            }

            return $chunk;
        }

        $deadline = $this->readDeadline > 0 ? $this->readDeadline : microtime(true) + max(1.0, $this->connectTimeout);
        $result = '';
        while (strlen($result) < $limit) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                throw new DataTransferException(
                    DataTransferException::EXCEPTION_RX_DATA,
                    'The broker did not answer the MQTT handshake in time.'
                );
            }

            $read = [$this->socket];
            $write = null;
            $except = null;
            $seconds = (int)floor($remaining);
            $microseconds = (int)(($remaining - $seconds) * 1_000_000);
            $ready = @stream_select($read, $write, $except, $seconds, $microseconds);
            if ($ready === false) {
                throw new DataTransferException(
                    DataTransferException::EXCEPTION_RX_DATA,
                    'Waiting for the MQTT socket failed.'
                );
            }
            if ($ready === 0) {
                throw new DataTransferException(
                    DataTransferException::EXCEPTION_RX_DATA,
                    'The broker did not answer the MQTT handshake in time.'
                );
            }

            $chunk = @fread($this->socket, $limit - strlen($result));
            if ($chunk === false) {
                throw new DataTransferException(
                    DataTransferException::EXCEPTION_RX_DATA,
                    'Reading data from the MQTT socket failed.'
                );
            }
            if ($chunk === '') {
                if (feof($this->socket)) {
                    throw new DataTransferException(
                        DataTransferException::EXCEPTION_RX_DATA,
                        'The broker closed the MQTT connection during the handshake.'
                    );
                }
                continue;
            }
            $result .= $chunk;
        }

        return $result;
    }
}
