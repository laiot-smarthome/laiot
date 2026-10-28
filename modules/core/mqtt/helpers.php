<?php

declare(strict_types=1);

use MajorDoMo\Mqtt\RuntimeState;
use MajorDoMo\Mqtt\TopicRepository;
use MajorDoMo\Mqtt\TopicService;
use MajorDoMo\Support\AppContext;

if (!function_exists('mqtt_publish')) {
    /**
     * `mqtt_publish('home/light', '1')` — отправить команду в брокер.
     *
     * Использует общее соединение worker-а, если он запущен в этом процессе,
     * иначе короткое соединение издателя. Ошибка брокера не бросается в
     * сценарий: возвращается false, причина попадает в RuntimeState.
     *
     * @param mixed $value Скаляр или массив (сохраняется как JSON).
     */
    function mqtt_publish(string $topic, mixed $value, int $qos = 0, bool $retain = false): bool
    {
        $app = AppContext::get();
        if ($app === null) {
            return false;
        }
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $payload = $encoded === false ? '' : $encoded;
        } elseif (is_bool($value)) {
            $payload = $value ? '1' : '0';
        } elseif ($value === null) {
            $payload = '';
        } else {
            $payload = (string)$value;
        }

        try {
            $app->container()->make(TopicService::class)->publish($topic, $payload, $qos, $retain);

            return true;
        } catch (Throwable $error) {
            $app->container()->make(RuntimeState::class)->error('mqtt_publish: ' . $error->getMessage());
            $app->container()->make(RuntimeState::class)->flush(true, false);

            return false;
        }
    }
}

if (!function_exists('mqtt_value')) {
    /**
     * Последнее известное значение топика из журнала MQTT.
     */
    function mqtt_value(string $path): ?string
    {
        $app = AppContext::get();
        if ($app === null) {
            return null;
        }

        $topic = $app->container()->make(TopicRepository::class)->findByPath(trim($path));

        return $topic === null ? null : (string)$topic['value'];
    }
}

if (!function_exists('mqtt_connected')) {
    /**
     * Держит ли процесс приёма активное соединение с брокером.
     */
    function mqtt_connected(): bool
    {
        $app = AppContext::get();
        if ($app === null) {
            return false;
        }
        $status = $app->container()->make(RuntimeState::class)->status();

        return (bool)($status['connected'] ?? false) && (bool)($status['worker_alive'] ?? false);
    }
}
