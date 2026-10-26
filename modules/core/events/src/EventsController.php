<?php

declare(strict_types=1);

namespace MajorDoMo\Modules\Core\Events;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Events\PropertyEventRepository;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Журнал цепочки событий свойства в alpha-layout.
 *
 * Страница читает core-таблицу `property_events` через репозиторий ядра:
 * собственных таблиц у модуля нет, в чужой SQL он не ходит. Записи создаёт
 * только `PropertyValueService`.
 */
final readonly class EventsController extends Controller
{
    private const TYPE_LABELS = [
        'property.changed' => 'Свойство',
        'handler.method' => 'Обработчик',
    ];

    private const STATUS_LABELS = [
        'changed' => 'Значение изменено',
        'stopped' => 'Цикл остановлен',
        'running' => 'Выполняется',
        'ok' => 'Обработчик выполнен',
        'error' => 'Ошибка обработчика',
        'missing' => 'Метод не найден',
        'skipped' => 'Обработчик пропущен',
    ];

    private const STATUS_TONES = [
        'changed' => 'info',
        'stopped' => 'warning',
        'running' => 'info',
        'ok' => 'success',
        'error' => 'danger',
        'missing' => 'danger',
        'skipped' => 'warning',
    ];

    public function __construct(
        private Factory $view,
        private PropertyEventRepository $events,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $type = trim((string)$request->query->get('type', ''));
        $status = trim((string)$request->query->get('status', ''));

        $content = $this->view->make('events::index', [
            'rows' => $this->events->latest(200, $search, $type, $status),
            'search' => $search,
            'type' => $type,
            'status' => $status,
            'typeLabels' => self::TYPE_LABELS,
            'statusLabels' => self::STATUS_LABELS,
            'statusTones' => self::STATUS_TONES,
        ])->render();

        return $this->panel->render(new PanelPage('События', $content, 'events'));
    }
}
