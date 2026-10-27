<?php

declare(strict_types=1);

namespace MajorDoMo\Settings;

use RuntimeException;

/**
 * Ошибки полей возвращаются форме без значений и секретов в сообщении.
 *
 * 2026-10-27 — добавлена адресная валидация настроек окружения.
 */
final class SettingsValidationException extends RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Проверьте выделенные поля.');
    }
}
