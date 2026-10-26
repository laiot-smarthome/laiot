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
use Throwable;

/**
 * Локации: помещения и их приоритет в интерфейсе.
 *
 * 2026-10-26 — добавлены поиск и количество объектов в помещении.
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

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));

        $content = $this->view->make('pages.locations.index', [
            'locations' => $this->locations->all($search),
            'objectCounts' => $this->safeCounts(),
            'search' => $search,
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
            $id = $this->locations->create(
                (string)$request->request->get('title', ''),
                (int)$request->request->get('priority', 0)
            );
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse('/locations/' . $id . '/edit');
    }

    public function edit(string $id): Response
    {
        $location = $this->locations->find((int)$id);

        return $location === null
            ? $this->panel->render(new PanelPage('Локация не найдена', '<p class="md-alert md-alert--danger">Локация не найдена.</p>', 'locations'), 404)
            : $this->form($location);
    }

    public function update(string $id, Request $request): Response
    {
        $location = $this->locations->find((int)$id);
        if ($location === null) {
            return new RedirectResponse('/locations');
        }

        try {
            $this->locations->update(
                (int)$id,
                (string)$request->request->get('title', ''),
                (int)$request->request->get('priority', 0)
            );
        } catch (InvalidArgumentException $error) {
            return $this->form($location, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse('/locations');
    }

    public function delete(string $id): Response
    {
        $this->locations->delete((int)$id);

        return new RedirectResponse('/locations');
    }

    /**
     * @param array{id:int,title:string,priority:int}|null $location
     * @param array<string, mixed>|null $draft
     */
    private function form(?array $location, ?string $error = null, ?array $draft = null, int $status = 200): Response
    {
        $content = $this->view->make('pages.locations.form', [
            'location' => $location,
            'draft' => $draft,
            'title' => (string)($draft['title'] ?? $location['title'] ?? ''),
            'priority' => (int)($draft['priority'] ?? $location['priority'] ?? 0),
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(
            new PanelPage($location === null ? 'Новая локация' : 'Локация: ' . (string)$location['title'], $content, 'locations'),
            $status
        );
    }

    /**
     * @return array<int,int>
     */
    private function safeCounts(): array
    {
        try {
            return $this->locations->objectCounts();
        } catch (Throwable) {
            return [];
        }
    }
}
