<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Http\Response;
use MajorDoMo\Http\Controller;

/**
 * Endpoint обновлений Yoyo-компонентов.
 *
 * Пока без auth/CSRF: это spike. На этапе 2 маршрут закрывается сеансом
 * администратора и CSRF, как и остальные изменяющие запросы.
 */
final readonly class YoyoController extends Controller
{
    public function __construct(private Yoyo $yoyo)
    {
    }

    public function __invoke(): Response
    {
        return $this->html($this->yoyo->update());
    }
}
