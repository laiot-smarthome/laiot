<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Locations\LocationRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Локации: список, создание, редактирование, soft delete.
 */
final readonly class LocationsController extends Controller
{
    public function __construct(
        private Factory $view,
        private LocationRepository $locations,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(): Response
    {
        $content = $this->view->make('pages.locations.index', [
            'locations' => $this->locations->all(),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Локации', $content, 'locations'));
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        try {
            $this->locations->create(
                (string)$request->request->get('title', ''),
                (int)$request->request->get('priority', 0)
            );
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), (string)$request->request->get('title', ''), 422);
        }

        return new RedirectResponse('/module/locations');
    }

    public function edit(string $id): Response
    {
        $location = $this->locations->find((int)$id);

        return $location === null
            ? $this->panel->render(new PanelPage('Локации', '<p class="md-alert md-alert--danger">Локация не найдена.</p>', 'locations'), 404)
            : $this->form($location);
    }

    public function update(string $id, Request $request): Response
    {
        $location = $this->locations->find((int)$id);
        if ($location === null) {
            return new RedirectResponse('/module/locations');
        }

        try {
            $this->locations->update(
                (int)$id,
                (string)$request->request->get('title', ''),
                (int)$request->request->get('priority', 0)
            );
        } catch (InvalidArgumentException $error) {
            return $this->form($location, $error->getMessage(), (string)$request->request->get('title', ''), 422);
        }

        return new RedirectResponse('/module/locations');
    }

    public function delete(string $id): Response
    {
        $this->locations->delete((int)$id);

        return new RedirectResponse('/module/locations');
    }

    /**
     * @param array{id:int,title:string,priority:int}|null $location
     */
    private function form(?array $location, ?string $error = null, ?string $title = null, int $status = 200): Response
    {
        $content = $this->view->make('pages.locations.form', [
            'location' => $location,
            'title' => $title ?? (string)($location['title'] ?? ''),
            'priority' => (int)($location['priority'] ?? 0),
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage($location === null ? 'Новая локация' : 'Локация', $content, 'locations'), $status);
    }
}
