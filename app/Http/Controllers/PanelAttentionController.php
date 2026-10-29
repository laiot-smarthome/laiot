<?php

declare(strict_types=1);

namespace LaIOT\Http\Controllers;

use LaIOT\Http\Controller;
use LaIOT\Services\Panel\PanelAttention;
use Symfony\Component\HttpFoundation\Response;

/**
 * Счётчики внимания для бокового меню оболочки.
 *
 * GET /api/v1/panel/attention — ошибки, сбойные задания и непрочитанные
 * уведомления. Эндпоинт принадлежит оболочке (как поиск в шапке и
 * колокольчик): страницы админки по-прежнему считают данные на сервере и
 * не заводят собственных JSON-роутов.
 */
final readonly class PanelAttentionController extends Controller
{
    public function __construct(private PanelAttention $attention)
    {
    }

    public function __invoke(): Response
    {
        return $this->json([
            'ok' => true,
            'data' => [
                'badges' => $this->attention->badges(),
            ],
        ]);
    }
}
