<?php

declare(strict_types=1);

namespace MajorDoMo\Install;

use RuntimeException;

/**
 * Ошибка установки, привязанная к полю формы.
 *
 * Поле нужно странице, чтобы подсветить конкретный ввод; CLI печатает
 * только текст. Отдельный класс не даёт контроллеру разбирать сообщения
 * регулярками.
 *
 * 2026-10-28 — ошибка установки вместо общего RuntimeException.
 */
final class InstallException extends RuntimeException
{
    public function __construct(string $message, private readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    public function field(): ?string
    {
        return $this->field;
    }
}
