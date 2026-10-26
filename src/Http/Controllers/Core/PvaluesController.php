<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Properties\PropertyValueService;
use MajorDoMo\Properties\ValueRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Значения свойств объектов: просмотр, правка, удаление.
 *
 * Правка идёт через `PropertyValueService`: значение, история, журнал
 * `property_events` и OnChange-метод свойства срабатывают так же, как при
 * записи из сценария.
 */
final readonly class PvaluesController extends Controller
{
    public function __construct(
        private Factory $view,
        private ValueRepository $values,
        private PropertyValueService $propertyValues,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $content = $this->view->make('pages.pvalues.index', [
            'values' => $this->values->all($search),
            'search' => $search,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Значения свойств', $content, 'pvalues'));
    }

    public function update(string $id, Request $request): Response
    {
        $row = $this->values->find((int)$id);
        if ($row !== null) {
            $this->propertyValues->setValue(
                $row['object_id'],
                $row['property_id'],
                (string)$request->request->get('value', ''),
                'panel:pvalues'
            );
        }

        return new RedirectResponse('/module/pvalues');
    }

    public function delete(string $id): Response
    {
        $this->values->delete((int)$id);

        return new RedirectResponse('/module/pvalues');
    }
}
