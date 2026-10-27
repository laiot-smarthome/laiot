<?php

declare(strict_types=1);

namespace MajorDoMo\Support;

/**
 * Транслитерация названий локаций в системные имена.
 *
 * Классы и объекты в MyDoMo называются только латиницей, цифрами и `_`:
 * по имени строятся `getGlobal()`, `callMethod()` и вызовы из сценариев.
 * Пользователь вводит «Спальня», а система создаёт `Spalnya`, поэтому
 * правило перевода должно быть одним для классов, объектов и проверок.
 *
 * 2026-10-27 — помощник создан для автоматических комнат и этажей.
 */
final class Transliteration
{
    /** Предел имени: 191 символ в БД, суффикс уникальности тоже должен влезть. */
    private const MAX_LENGTH = 150;

    /** @var array<string,string> */
    private const MAP = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        // Буквы, которые встречаются в названиях соседних языков.
        'і' => 'i', 'ї' => 'yi', 'є' => 'ye', 'ґ' => 'g', 'ў' => 'u',
    ];

    /**
     * Системное имя: транслит, латиница/цифры/_, первая — буква или _.
     *
     * @param string $fallback Префикс для названий, начинающихся с цифры.
     */
    public static function identifier(string $value, string $fallback = 'Room'): string
    {
        $value = trim($value);
        $map = self::map();
        $slug = strtr($value, $map);
        $slug = (string)preg_replace('/[^A-Za-z0-9_]+/u', '_', $slug);
        $slug = trim((string)preg_replace('/_+/', '_', $slug), '_');
        if ($slug === '') {
            return self::fit($fallback);
        }
        if (preg_match('/^[0-9]/', $slug) === 1) {
            $slug = $fallback . '_' . $slug;
        }
        $slug = mb_substr($slug, 0, self::MAX_LENGTH);
        $slug = mb_strtoupper(mb_substr($slug, 0, 1)) . mb_substr($slug, 1);

        return Identifier::isValid($slug) ? $slug : self::fit($fallback);
    }

    /**
     * Свободное имя: базовое, затем `_2`, `_3` и далее.
     *
     * @param callable(string): bool $taken Занято ли имя (без учёта регистра).
     */
    public static function unique(string $base, callable $taken, int $limit = 999): string
    {
        if (!$taken($base)) {
            return $base;
        }
        for ($index = 2; $index <= $limit; $index++) {
            $suffix = '_' . $index;
            $candidate = mb_substr($base, 0, self::MAX_LENGTH - mb_strlen($suffix)) . $suffix;
            if (!$taken($candidate)) {
                return $candidate;
            }
        }

        // Практический предел исчерпан: добавляем короткий случайный хвост,
        // чтобы создание комнаты не падало на массовых одноимённых локациях.
        return mb_substr($base, 0, self::MAX_LENGTH - 9) . '_' . bin2hex(random_bytes(4));
    }

    /**
     * @return array<string,string>
     */
    private static function map(): array
    {
        $map = self::MAP;
        foreach (self::MAP as $from => $to) {
            $map[mb_strtoupper($from)] = $to === ''
                ? ''
                : mb_strtoupper(mb_substr($to, 0, 1)) . mb_substr($to, 1);
        }

        return $map;
    }

    private static function fit(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_]+/', '_', $value) ?? '';
        $value = trim((string)preg_replace('/_+/', '_', $value), '_');
        if ($value === '' || !Identifier::isValid($value)) {
            $value = 'Room';
        }

        return mb_substr($value, 0, self::MAX_LENGTH);
    }
}
