<?php

declare(strict_types=1);

namespace LaIOT\Services\Locations;

/**
 * Роль свойства в активности комнаты.
 *
 * Роль задаётся у свойства и говорит системе, что значение участвует в
 * сборе состояния комнаты. Свет/обогрев/охлаждение попадают в одноимённые
 * флаги комнаты, датчик только продлевает активность: по роли «датчик»
 * нельзя вывести постоянный флаг, не зная смысла конкретного значения.
 *
 * 2026-10-27 — роли добавлены вместе с автоматическим учётом активности.
 */
final class RoomRole
{
    public const LIGHT = 'light';

    public const HEATING = 'heating';

    public const COOLING = 'cooling';

    public const SENSOR = 'sensor';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::LIGHT, self::HEATING, self::COOLING, self::SENSOR];
    }

    /** @return array<string,string> */
    public static function labels(): array
    {
        return [
            self::LIGHT => 'Свет',
            self::HEATING => 'Обогрев',
            self::COOLING => 'Кондиционер / охлаждение',
            self::SENSOR => 'Датчик активности',
        ];
    }

    public static function label(string $role): string
    {
        return self::labels()[$role] ?? '';
    }

    /**
     * Неизвестная роль превращается в «не участвует»: опечатка в форме не
     * должна незаметно включать свойство в автоматику.
     */
    public static function normalize(string $role): string
    {
        $role = trim($role);

        return in_array($role, self::all(), true) ? $role : '';
    }

    /**
     * Флаг комнаты, в который складывается роль; null — только таймер.
     */
    public static function roomFlag(string $role): ?string
    {
        return match ($role) {
            self::LIGHT => 'isLight',
            self::HEATING => 'isHeating',
            self::COOLING => 'isCondition',
            default => null,
        };
    }
}
