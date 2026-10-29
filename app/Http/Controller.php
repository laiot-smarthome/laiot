<?php

declare(strict_types=1);

namespace LaIOT\Http;

use LaIOT\Http\Concerns\CreatesResponses;

/**
 * Базовый контроллер LaIOT.
 *
 * Наследование даёт единые ответы и точку для будущих общих зависимостей
 * (пользователь, запрос, политика). Контроллеры не работают с SQL напрямую.
 */
abstract readonly class Controller
{
    use CreatesResponses;
}
