<?php

declare(strict_types=1);

namespace MajorDoMo\Mqtt;

use Closure;
use RuntimeException;

/**
 * Реестр MQTT-интеграций текущего процесса.
 *
 * Заполняется при boot включённых модулей. Выключенный модуль не регистрирует
 * себя, поэтому его топики не попадают в подписку MQTT-цикла: одно состояние
 * реестра модулей управляет и маршрутами, и транспортом.
 *
 * Интеграция регистрируется лениво (`addLazy`): её сервисы могут зависеть от
 * ModuleManager, который в этот момент ещё собирается. Ранняя сборка дала бы
 * рекурсию через NotificationService, поэтому фабрика вызывается при первом
 * обращении — после завершения boot.
 *
 * Реестр не является брокером: он не хранит сообщения и не повторяет их.
 * Долговечность обеспечивает очередь самого модуля-интеграции.
 *
 * 2026-10-28 — реестр создан вместо alpha `MqttIntegrations`.
 */
final class Integrations
{
    /** @var array<string, Integration|Closure():Integration> */
    private array $items = [];

    /** @var array<string, Integration> */
    private array $resolved = [];

    public function add(Integration $integration): void
    {
        $this->register($integration->id(), $integration);
    }

    /**
     * Регистрирует интеграцию, не создавая её сервисы во время boot модулей.
     *
     * @param Closure():Integration $factory
     */
    public function addLazy(string $id, Closure $factory): void
    {
        $this->register($id, $factory);
    }

    private function register(string $id, Integration|Closure $integration): void
    {
        $id = trim($id);
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $id)) {
            throw new RuntimeException('Некорректный id MQTT-интеграции: ' . $id);
        }

        $this->items[$id] = $integration;
        unset($this->resolved[$id]);
    }

    /**
     * @return array<string, Integration>
     */
    public function all(): array
    {
        foreach ($this->items as $id => $integration) {
            if ($integration instanceof Integration) {
                $this->resolved[$id] = $integration;

                continue;
            }
            $instance = $integration();
            if (!$instance instanceof Integration) {
                throw new RuntimeException('Фабрика MQTT-интеграции вернула не Integration: ' . $id);
            }
            if ($instance->id() !== $id) {
                throw new RuntimeException('Id MQTT-интеграции не совпадает с регистрацией: ' . $id);
            }
            $this->resolved[$id] = $instance;
        }

        return $this->resolved;
    }

    /**
     * Объединённый список фильтров для подписки на брокере.
     *
     * @return list<string>
     */
    public function topics(): array
    {
        $topics = [];
        foreach ($this->all() as $integration) {
            foreach ($integration->topics() as $topic) {
                $topic = trim($topic);
                if ($topic !== '' && !in_array($topic, $topics, true)) {
                    $topics[] = $topic;
                }
            }
        }

        return $topics;
    }

    /**
     * Владелец топика или null, если сообщение относится к привязкам MQTT.
     */
    public function owner(string $topic): ?Integration
    {
        foreach ($this->all() as $integration) {
            if ($integration->owns($topic)) {
                return $integration;
            }
        }

        return null;
    }

    /**
     * Отдаёт сообщение владельцу. Возвращает false, если топик ничей.
     */
    public function dispatch(string $topic, string $payload): bool
    {
        $owner = $this->owner($topic);
        if ($owner === null) {
            return false;
        }
        $owner->enqueue($topic, $payload);

        return true;
    }
}
