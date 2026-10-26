<?php

declare(strict_types=1);

namespace MajorDoMo\Http;

use MajorDoMo\Http\Concerns\CreatesResponses;

/**
 * Базовый контроллер MyDoMo.
 *
 * Наследование даёт единые ответы и точку для будущих общих зависимостей
 * (пользователь, запрос, политика). Контроллеры не работают с SQL напрямую.
 */
abstract readonly class Controller
{
    use CreatesResponses;
}
