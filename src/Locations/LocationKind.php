<?php

declare(strict_types=1);

namespace MajorDoMo\Locations;

use InvalidArgumentException;

/**
 * Тип локации: укрупнённый блок или помещение.
 *
 * Иерархия ограничена тремя уровнями (дом → этаж → комната), чтобы будущая
 * свёртка по этажу не зависела от произвольной глубины дерева. Комната —
 * единственный тип, для которого создаются объект и класс активности.
 *
 * 2026-10-27 — тип добавлен вместе с иерархией локаций.
 */
final class LocationKind
{
    public const HOUSE = 'house';

    public const FLOOR = 'floor';

    public const ROOM = 'room';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::HOUSE, self::FLOOR, self::ROOM];
    }

    /** @return array<string,string> */
    public static function labels(): array
    {
        return [
            self::HOUSE => 'Дом',
            self::FLOOR => 'Этаж',
            self::ROOM => 'Комната',
        ];
    }

    public static function label(string $kind): string
    {
        return self::labels()[$kind] ?? $kind;
    }

    public static function normalize(string $kind): string
    {
        $kind = trim($kind);

        return in_array($kind, self::all(), true) ? $kind : self::ROOM;
    }

    public static function assert(string $kind): string
    {
        $kind = trim($kind);
        if (!in_array($kind, self::all(), true)) {
            throw new InvalidArgumentException('Неизвестный тип локации.');
        }

        return $kind;
    }

    /**
     * Может ли локация быть родителем для указанного типа.
     *
     * Дом стоит на верхнем уровне, этаж — под домом, комната — под этажом
     * или домом. Комната не может содержать другие локации.
     */
    public static function canBeParent(string $parentKind, string $childKind): bool
    {
        return match ($childKind) {
            self::HOUSE => false,
            self::FLOOR => $parentKind === self::HOUSE,
            self::ROOM => in_array($parentKind, [self::HOUSE, self::FLOOR], true),
            default => false,
        };
    }
}
