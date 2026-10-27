<?php

declare(strict_types=1);

namespace MajorDoMo\Support;

use InvalidArgumentException;

/**
 * Системное имя класса, объекта, свойства или метода.
 *
 * 2026-10-27 — имена ограничены латиницей: по ним строятся вызовы
 * setGlobal()/getGlobal(), callMethod() и PHP-методы, поэтому кириллица,
 * пробелы и дефисы ломают пользовательский код. Проверка одна для всех
 * репозиториев, чтобы правило не расходилось между формами.
 */
final class Identifier
{
    public const MAX_LENGTH = 191;

    /** Первая буква или подчёркивание, дальше латиница, цифры и подчёркивание. */
    public const PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * Нормализует и проверяет имя; возвращает его без внешних пробелов.
     *
     * @param string $subject название сущности в винительном падеже для текста
     *                        ошибки: «класса», «объекта», «свойства», «метода»
     */
    public static function assert(string $value, string $subject): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException('Укажите название ' . $subject . '.');
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(
                'Название ' . $subject . ' должно быть не длиннее ' . self::MAX_LENGTH . ' символов.'
            );
        }
        if (!self::isValid($value)) {
            throw new InvalidArgumentException(
                'Название ' . $subject . ': только латиница, цифры и _, первая — буква или _. '
                . 'Пробелы и кириллица недопустимы — по имени вызываются setGlobal() и методы.'
            );
        }

        return $value;
    }

    public static function isValid(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
    }
}
