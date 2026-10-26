<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Http\Response;
use MajorDoMo\Http\Controller;

/**
 * Endpoint обновлений Yoyo-компонентов.
 *
 * Yoyo/htmx ходит GET-запросом; маршрут закрыт auth-сессией. POST-вариант с
 * CSRF оставлен для изменяющей семантики. Yoyo — только UI-состояние, не
 * бизнес-операции.
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
