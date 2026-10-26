<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

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
 * Свойства классов: каталог с поиском и фильтром по классу.
 *
 * 2026-10-26 — добавлены поиск, фильтр, типы данных и OnChange в списке.
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

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $classId = max(0, (int)$request->query->get('class_id', 0));
        $classes = $this->classes->all();

        $names = [0 => 'Глобальные'];
        foreach ($classes as $class) {
            $names[$class['id']] = $class['title'];
        }

        $content = $this->view->make('pages.properties.index', [
            'properties' => $this->properties->all($search, $classId),
            'classes' => $classes,
            'classNames' => $names,
            'search' => $search,
            'classId' => $classId,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Свойства', $content, 'properties'));
    }

    public function create(Request $request): Response
    {
        // Класс можно передать ссылкой из карточки класса: селект уже заполнен.
        $classId = max(0, (int)$request->query->get('class_id', 0));

        return $this->form(null, null, $classId > 0 ? ['class_id' => $classId] : null);
    }

    public function store(Request $request): Response
    {
        try {
            $id = $this->properties->create(
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

        return new RedirectResponse('/properties/' . $id . '/edit');
    }

    public function edit(string $id): Response
    {
        $property = $this->properties->find((int)$id);

        return $property === null
            ? $this->panel->render(new PanelPage('Свойство не найдено', '<p class="md-alert md-alert--danger">Свойство не найдено.</p>', 'properties'), 404)
            : $this->form($property);
    }

    public function update(string $id, Request $request): Response
    {
        $property = $this->properties->find((int)$id);
        if ($property === null) {
            return new RedirectResponse('/properties');
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

        return new RedirectResponse('/properties');
    }

    public function delete(string $id): Response
    {
        $this->properties->delete((int)$id);

        return new RedirectResponse('/properties');
    }

    /**
     * @param array{id:int,class_id:int,title:string,description:string,data_type:string,keep_history:bool,on_change:string}|null $property
     * @param array<string, mixed>|null $draft
     */
    private function form(?array $property, ?string $error = null, ?array $draft = null, int $status = 200): Response
    {
        $content = $this->view->make('pages.properties.form', [
            'property' => $property,
            'draft' => $draft,
            'classes' => $this->classes->all(),
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(
            new PanelPage($property === null ? 'Новое свойство' : 'Свойство: ' . (string)$property['title'], $content, 'properties'),
            $status
        );
    }
}
