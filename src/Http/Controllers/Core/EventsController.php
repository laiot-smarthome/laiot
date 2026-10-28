<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Журнал цепочки событий свойства.
 *
 * Данные и фильтры живут в Yoyo-компоненте `events-table`: поиск и выбор
 * типа/статуса перерисовывают таблицу без перезагрузки страницы и без
 * отдельного JSON-эндпоинта. Контроллер отдаёт страницу и начальное состояние
 * из query string (чтобы ссылки с фильтром оставались рабочими).
 *
 * 2026-10-27 — таблица событий переведена на Yoyo-компонент.
 * 2026-10-28 — прямые ссылки открывают цепочку и сохраняют страницу списка.
 */
final readonly class EventsController extends Controller
{
    public function __construct(
        private Factory $view,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $content = $this->view->make('pages.events.index', [
            'search' => trim((string)$request->query->get('q', '')),
            'type' => trim((string)$request->query->get('type', '')),
            'status' => trim((string)$request->query->get('status', '')),
            'selectedId' => max(0, (int)$request->query->get('chain', 0)),
            'page' => max(1, (int)$request->query->get('page', 1)),
        ])->render();

        return $this->panel->render(new PanelPage('События', $content, 'events'));
    }
}
