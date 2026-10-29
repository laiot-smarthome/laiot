<?php

declare(strict_types=1);

namespace LaIOT\Services\Modules;

use Closure;
use RuntimeException;

/**
 * Реестр обработчиков изменений свойств по id модуля.
 *
 * Заполняется при boot из манифестов (`property_subscriber`), но остаётся
 * обычным сервисом контейнера: тест или будущий модуль может зарегистрировать
 * обработчик напрямую, не создавая каталог модуля.
 *
 * Обработчик создаётся лениво: подписка есть у многих свойств, а модуль,
 * который сейчас не включён или не установлен, не должен грузиться на каждой
 * записи значения.
 *
 * 2026-10-27 — реестр создан для доставки изменений свойства модулям.
 */
final class PropertySubscriberRegistry
{
    /** @var array<string, Closure():mixed> */
    private array $factories = [];

    /** @var array<string, PropertySubscriber> */
    private array $resolved = [];

    /**
     * Фабрика возвращает обработчик; тип проверяется при первом вызове, чтобы
     * ошибка модуля не ломала регистрацию остальных.
     *
     * @param Closure():mixed $factory
     */
    public function register(string $moduleId, Closure $factory): void
    {
        $moduleId = trim($moduleId);
        if ($moduleId === '') {
            throw new RuntimeException('Пустой id модуля-подписчика.');
        }

        $this->factories[$moduleId] = $factory;
        unset($this->resolved[$moduleId]);
    }

    public function has(string $moduleId): bool
    {
        return isset($this->factories[$moduleId]);
    }

    /**
     * @return list<string> Зарегистрированные id модулей.
     */
    public function modules(): array
    {
        return array_keys($this->factories);
    }

    public function get(string $moduleId): ?PropertySubscriber
    {
        if (isset($this->resolved[$moduleId])) {
            return $this->resolved[$moduleId];
        }

        $factory = $this->factories[$moduleId] ?? null;
        if ($factory === null) {
            return null;
        }

        $subscriber = $factory();
        if (!$subscriber instanceof PropertySubscriber) {
            throw new RuntimeException('Модуль ' . $moduleId . ' вернул не PropertySubscriber.');
        }
        $this->resolved[$moduleId] = $subscriber;

        return $subscriber;
    }
}
