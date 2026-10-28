<?php

declare(strict_types=1);

namespace MajorDoMo\Mqtt;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use RuntimeException;

/**
 * Долгоживущее MQTT-соединение на php-mqtt/client.
 *
 * Клиент библиотеки работает с неблокирующим сокетом: `loopOnce()` читает
 * всё доступное и сразу возвращается, поэтому цикл приёма может между
 * пакетами отправлять команды из очереди, обслуживать keepalive и проверять
 * настройки. Это и есть быстрый путь: одно соединение на приём и отправку,
 * без TCP-подключения на каждую команду.
 *
 * 2026-10-27 — замена Bluerhinos/phpMQTT на поддерживаемый пакет.
 */
final class Broker
{
    private ?MqttClient $client = null;

    private ?string $configuration = null;

    public function __construct(private readonly Settings $settings)
    {
    }

    /**
     * @param (callable(string, string, bool, array<int, string>): void)|null $onMessage
     */
    public function connect(?callable $onMessage = null): bool
    {
        $this->close();
        $host = trim($this->settings->string('host')) ?: 'localhost';
        $port = max(1, min(65535, $this->settings->int('port')));
        $clientId = trim($this->settings->string('client_id')) ?: 'MyDoMo MQTT';
        // Один client id на два процесса заставляет брокер выключать
        // предыдущее соединение. PID и случайный хвост это исключают.
        $clientId .= ' #' . (getmypid() ?: 0) . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        $client = new SocketClient($host, $port, $clientId, MqttClient::MQTT_3_1_1, 5.0);

        $connection = (new ConnectionSettings())
            ->setKeepAliveInterval(max(1, min(600, $this->settings->int('keepalive'))))
            ->setConnectTimeout(5)
            ->setSocketTimeout(5)
            ->setResendTimeout(5)
            ->setReconnectAutomatically(false);

        if ($this->settings->bool('auth')) {
            $connection = $connection
                ->setUsername($this->settings->string('username'))
                ->setPassword($this->settings->string('password'));
        }

        if ($this->settings->bool('tls')) {
            $verify = $this->settings->bool('tls_verify');
            $connection = $connection
                ->setUseTls(true)
                ->setTlsVerifyPeer($verify)
                ->setTlsVerifyPeerName($verify)
                ->setTlsSelfSignedAllowed(!$verify);
            $ca = trim($this->settings->string('tls_ca'));
            if ($ca !== '') {
                $connection = $connection->setTlsCertificateAuthorityFile($ca);
            }
        }

        $client->connect($connection, true);

        $filters = $this->settings->queries();
        if ($onMessage !== null) {
            // php-mqtt принимает только Closure, а не произвольный callable.
            $callback = $onMessage instanceof \Closure ? $onMessage : \Closure::fromCallable($onMessage);
            foreach ($filters as $filter) {
                $client->subscribe($filter, $callback, 0);
            }
        }

        $this->client = $client;
        $this->configuration = $this->configurationHash();

        return true;
    }

    public function isConnected(): bool
    {
        return $this->client !== null && $this->client->isConnected();
    }

    /**
     * Читает доступные пакеты. Без данных спит $sleepMicroseconds, чтобы
     * рабочий цикл не занимал ядро процессора.
     */
    public function pump(float $loopStartedAt, int $sleepMicroseconds = 20000): void
    {
        if ($this->client === null) {
            throw new RuntimeException('MQTT-клиент не подключён.');
        }
        $this->client->loopOnce($loopStartedAt, true, max(0, $sleepMicroseconds));
    }

    /**
     * Обслуживание без ожидания: подтверждения, ping и закрытие сокета.
     */
    public function tick(): void
    {
        if ($this->client === null) {
            return;
        }
        $this->client->loopOnce(microtime(true), false);
    }

    public function publish(string $topic, string $message, int $qos = 0, bool $retain = false): void
    {
        if ($this->client === null) {
            throw new RuntimeException('MQTT-клиент не подключён.');
        }
        $this->client->publish($topic, $message, max(0, min(2, $qos)), $retain);
    }

    public function close(): void
    {
        if ($this->client === null) {
            return;
        }
        try {
            if ($this->client->isConnected()) {
                $this->client->disconnect();
            }
        } catch (\Throwable) {
            // Сокет мог быть уже закрыт брокером: соединение всё равно сбрасываем.
        }
        $this->client = null;
        $this->configuration = null;
    }

    /**
     * Отпечаток параметров соединения: смена настроек переподключает цикл.
     */
    public function configurationHash(): string
    {
        return hash('sha256', json_encode([
            $this->settings->string('host'),
            $this->settings->int('port'),
            $this->settings->string('client_id'),
            $this->settings->bool('auth'),
            $this->settings->string('username'),
            $this->settings->string('password'),
            $this->settings->bool('tls'),
            $this->settings->string('tls_ca'),
            $this->settings->bool('tls_verify'),
            $this->settings->int('keepalive'),
            $this->settings->queries(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    public function configurationChanged(): bool
    {
        return $this->configuration !== null && $this->configuration !== $this->configurationHash();
    }
}
