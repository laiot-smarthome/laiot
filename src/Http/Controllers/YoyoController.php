<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Clickfwd\Yoyo\Services\Response as YoyoResponse;
use Clickfwd\Yoyo\Yoyo;
use Illuminate\Http\Response;
use MajorDoMo\Http\Controller;

/**
 * Endpoint обновлений Yoyo-компонентов.
 *
 * Yoyo/htmx ходит GET-запросом; маршрут закрыт auth-сессией. POST-вариант с
 * CSRF оставлен для изменяющей семантики. Yoyo — только UI-состояние, не
 * бизнес-операции.
 *
 * 2026-10-28 — заголовки событий Yoyo перенесены в HTTP-ответ приложения.
 */
final readonly class YoyoController extends Controller
{
    public function __construct(private Yoyo $yoyo)
    {
    }

    public function __invoke(): Response
    {
        $content = $this->yoyo->update();
        $yoyoResponse = YoyoResponse::getInstance();
        $headers = [];
        foreach ($yoyoResponse->getHeaders() as $name => $value) {
            $headers[(string)$name] = is_array($value) ? (string)json_encode($value) : (string)$value;
        }

        return $this->html($content, (int)$yoyoResponse->getStatusCode(), $headers);
    }
}
