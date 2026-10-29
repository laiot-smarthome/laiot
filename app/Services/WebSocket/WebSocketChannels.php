<?php

declare(strict_types=1);

namespace LaIOT\Services\WebSocket;

use InvalidArgumentException;

/**
 * Канал WebSocket: «Объект.свойство» и общий канал system.
 *
 * Имена вместо числовых id: страница и сообщение читаются одинаково
 * (`Light.status`), а подписка не зависит от внутренних идентификаторов.
 * Системные события (`system.minute`, `module.mqtt.ready` и т.п.) идут по
 * общему каналу `system`, его получает каждое подключение панели.
 *
 * 2026-10-29 — единый формат каналов, имена и системный канал.
 */
final class WebSocketChannels
{
    public const PROTOCOL = 'laiot.v1';
    public const SYSTEM = 'system';

    private const NAME = '[A-Za-z_][A-Za-z0-9_]*';

    public static function key(string $objectTitle, string $propertyTitle): string
    {
        return $objectTitle . '.' . $propertyTitle;
    }

    /**
     * @return array{0:string,1:string}|null
     */
    public static function parse(string $channel): ?array
    {
        if (preg_match('/^(' . self::NAME . ')\.(' . self::NAME . ')$/D', $channel, $match) !== 1) {
            return null;
        }

        return [$match[1], $match[2]];
    }

    public static function isSystem(string $channel): bool
    {
        return $channel === self::SYSTEM;
    }

    /**
     * Проверяет, убирает повторы и ограничивает число каналов.
     *
     * @param array<int, mixed> $channels
     * @return list<string>
     */
    public static function normalize(array $channels, int $max): array
    {
        $max = max(1, $max);
        $result = [];
        foreach ($channels as $channel) {
            if (!is_string($channel)) {
                continue;
            }
            $channel = trim($channel);
            if (!self::isSystem($channel) && self::parse($channel) === null) {
                continue;
            }
            if (!in_array($channel, $result, true)) {
                $result[] = $channel;
            }
            if (count($result) > $max) {
                throw new InvalidArgumentException('Слишком много каналов для одного подключения: максимум ' . $max . '.');
            }
        }

        if ($result === []) {
            throw new InvalidArgumentException('Не передано ни одного корректного канала.');
        }

        return self::withSystem($result);
    }

    /**
     * Канал системных событий добавляется всем подключениям панели.
     *
     * @param list<string> $channels
     * @return list<string>
     */
    public static function withSystem(array $channels): array
    {
        if (!in_array(self::SYSTEM, $channels, true)) {
            $channels[] = self::SYSTEM;
        }

        return $channels;
    }

    /**
     * Разбирает JSON из тикета, не доверяя содержимому БД.
     *
     * @return list<string>
     */
    public static function fromJson(mixed $json, int $max): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        try {
            return self::normalize($decoded, $max);
        } catch (InvalidArgumentException) {
            return [];
        }
    }
}
