<?php

declare(strict_types=1);

namespace LaIOT\Http\Controllers;

use Illuminate\Http\Request;
use LaIOT\Http\Controller;
use LaIOT\Services\Search\PanelSearch;
use Symfony\Component\HttpFoundation\Response;

/**
 * Поиск по админке для шапки и полного drawer.
 *
 * Отдаёт конверт `{ok,data}`, который ожидает `window.MDJAdminApi`: JS
 * разворачивает `data` и сразу получает `sections/total/counts`.
 */
final readonly class PanelSearchController extends Controller
{
    public function __construct(private PanelSearch $search)
    {
    }

    public function __invoke(Request $request): Response
    {
        $query = (string)$request->query->get('q', '');
        $limit = (int)$request->query->get('limit', PanelSearch::DEFAULT_LIMIT);

        return $this->json([
            'ok' => true,
            'data' => $this->search->search($query, $limit),
        ]);
    }
}
