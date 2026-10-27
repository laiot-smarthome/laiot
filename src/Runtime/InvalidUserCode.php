<?php

declare(strict_types=1);

namespace MajorDoMo\Runtime;

use InvalidArgumentException;

/**
 * Пользовательский код не сохранён из-за синтаксической ошибки.
 *
 * Отдельный тип нужен, чтобы форма могла подсветить строку, а не только
 * показать текст ошибки. Свойство называется `codeLine`, а не `line`:
 * `Exception::getLine()` сообщает строку PHP-файла, а здесь нужна строка
 * пользовательского кода.
 *
 * 2026-10-27 — исключение введено вместе с проверкой синтаксиса кода.
 */
final class InvalidUserCode extends InvalidArgumentException
{
    public function __construct(
        public readonly int $codeLine,
        string $message
    ) {
        parent::__construct($message);
    }
}
