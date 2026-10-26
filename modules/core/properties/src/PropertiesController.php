<?php

declare(strict_types=1);

namespace MajorDoMo\Modules\Core\Properties;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Properties\PropertyRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Свойства классов: CRUD. Значения свойств — следующий слой.
 */
final readonly class PropertiesController extends Controller
{
    public function __construct(
        private Factory $view,
        private PropertyRepository $properties,
        private ClassRepository $classes,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(): Response
    {
        $classes = [];
        foreach ($this->classes->all() as $class) {
            $classes[$class['id']] = $class['title'];
        }

        $content = $this->view->make('properties::index', [
            'properties' => $this->properties->all(),
            'classes' => $classes,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Свойства', $content, 'properties'));
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        try {
            $this->properties->create(
                (int)$request->request->get('class_id', 0),
                (string)$request->request->get('title', ''),
                (string)$request->request->get('description', ''),
                (string)$request->request->get('data_type', 'string'),
                (string)$request->request->get('keep_history', '') === '1',
                (string)$request->request->get('on_change', '')
            );
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse('/module/properties');
    }

    public function edit(string $id): Response
    {
        $property = $this->properties->find((int)$id);

        return $property === null
            ? $this->panel->render(new PanelPage('Свойства', '<p class="md-alert md-alert--danger">Свойство не найдено.</p>', 'properties'), 404)
            : $this->form($property);
    }

    public function update(string $id, Request $request): Response
    {
        $property = $this->properties->find((int)$id);
        if ($property === null) {
            return new RedirectResponse('/module/properties');
        }

        try {
            $this->properties->update(
                (int)$id,
                (int)$request->request->get('class_id', 0),
                (string)$request->request->get('title', ''),
                (string)$request->request->get('description', ''),
                (string)$request->request->get('data_type', 'string'),
                (string)$request->request->get('keep_history', '') === '1',
                (string)$request->request->get('on_change', '')
            );
        } catch (InvalidArgumentException $error) {
            return $this->form($property, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse('/module/properties');
    }

    public function delete(string $id): Response
    {
        $this->properties->delete((int)$id);

        return new RedirectResponse('/module/properties');
    }

    /**
     * @param array{id:int,class_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string}|null $property
     * @param array<string, mixed>|null $draft
     */
    private function form(?array $property, ?string $error = null, ?array $draft = null, int $status = 200): Response
    {
        $content = $this->view->make('properties::form', [
            'property' => $property,
            'draft' => $draft,
            'classes' => $this->classes->all(),
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage($property === null ? 'Новое свойство' : 'Свойство', $content, 'properties'), $status);
    }
}
