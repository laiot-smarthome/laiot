<?php

declare(strict_types=1);

namespace MajorDoMo\Install;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;
use MajorDoMo\Auth\UserRepository;

/**
 * Предметное правило пароля: длина, спецсимвол и лимит bcrypt в байтах.
 *
 * Штатные правила покрывают длину и regex, но лимит 72 байта считается по
 * байтам, а не по символам, поэтому проверка остаётся одна — та же, что у
 * UserRepository при создании пользователя.
 *
 * 2026-10-28 — правило для общего валидатора установщика.
 */
final readonly class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            UserRepository::assertPassword((string)$value);
        } catch (InvalidArgumentException $error) {
            $fail($error->getMessage());
        }
    }
}
