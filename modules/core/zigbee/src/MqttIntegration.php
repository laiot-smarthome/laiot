<?php

declare(strict_types=1);

namespace MajorDoMo\Zigbee;

use InvalidArgumentException;
use MajorDoMo\Mqtt\Integration;
use MajorDoMo\Queue\Queue;

/**
 * Zigbee как владелец топиков на общем MQTT-соединении.
 *
 * MQTT-цикл вызывает `enqueue()` в процессе приёма: здесь нельзя разбирать
 * сообщение, писать свойства и вызывать пользовательский PHP. Поэтому
 * сообщение превращается в задание очереди `module.zigbee`, а разбор идёт в
 * worker-е модуля. Это та же семантика «как минимум один раз», что у alpha,
 * но без общей `core_runtime` и без собственного MQTT-клиента.
 *
 * Большой retained-пакет `bridge/devices` не помещается в `text` задания
 * целиком, поэтому сжимается и кодируется base64; обработчик распознаёт
 * формат по полю `encoding`.
 *
 * 2026-10-28 — интеграция создана при переносе Zigbee на общий транспорт.
 */
final class MqttIntegration implements Integration
{
    public const JOB = 'zigbee.message';

    public const QUEUE = 'module.zigbee';

    public const ENCODING = 'deflate-base64';

    /** Верхняя граница пакета, который имеет смысл ставить в очередь. */
    private const MAX_PAYLOAD = 4_194_304;

    public function __construct(
        private readonly Service $service,
        private readonly Queue $queue
    ) {
    }

    public function id(): string
    {
        return 'zigbee';
    }

    public function topics(): array
    {
        return $this->service->topics();
    }

    public function owns(string $topic): bool
    {
        return $this->service->owns($topic);
    }

    public function enqueue(string $topic, string $payload): void
    {
        if (strlen($payload) > self::MAX_PAYLOAD) {
            throw new InvalidArgumentException(
                'Сообщение Zigbee больше ' . self::MAX_PAYLOAD . ' байт и не поставлено в очередь.'
            );
        }
        $data = ['topic' => $topic, 'payload' => $payload];
        if (strlen($payload) > 32_000 && function_exists('gzdeflate')) {
            $packed = gzdeflate($payload, 6);
            if ($packed === false) {
                throw new InvalidArgumentException('Не удалось сжать сообщение Zigbee.');
            }
            $data = [
                'topic' => $topic,
                'payload' => base64_encode($packed),
                'encoding' => self::ENCODING,
            ];
        }
        // payload очереди — text (64 КБ в MySQL). Сжатие покрывает
        // bridge/devices, но случайный пакет больше лимита лучше отклонить
        // явно, чем получить обрезанную запись.
        if (strlen($data['payload']) > 60_000) {
            throw new InvalidArgumentException('Сообщение Zigbee не помещается в задание очереди после сжатия.');
        }
        $this->queue->push(self::QUEUE, self::JOB, $data);
    }

    /**
     * Восстанавливает payload задания. Публичный, чтобы тест мог проверить
     * формат без запуска очереди.
     *
     * @param array<string, mixed> $data
     */
    public static function decodePayload(array $data): string
    {
        $payload = (string)($data['payload'] ?? '');
        if ((string)($data['encoding'] ?? '') !== self::ENCODING) {
            return $payload;
        }
        $packed = base64_decode($payload, true);
        if ($packed === false || !function_exists('gzinflate')) {
            throw new InvalidArgumentException('Сообщение Zigbee повреждено: не удалось распаковать payload.');
        }
        $decoded = gzinflate($packed);
        if ($decoded === false) {
            throw new InvalidArgumentException('Сообщение Zigbee повреждено: gzinflate вернул ошибку.');
        }

        return $decoded;
    }
}
