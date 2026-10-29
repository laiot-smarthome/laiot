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
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Panel\ClassCatalog;
use MajorDoMo\Panel\RenameNotice;
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertySubscriptionRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Единый раздел «Классы»: дерево классов с подклассами, карточка класса,
 * его объекты, свойства и методы.
 *
 * 2026-10-29 — каталог собирает ClassCatalog; ошибки чтения больше не скрываются.
 *
 * 2026-10-27 — объекты, свойства, методы, значения и история больше не
 * отдельные пункты меню: пользователь работает через класс, а наследование
 * показывается в карточке класса и его объекта.
 * 2026-10-27 — дерево на ПК содержит объекты дочерними строками, на телефоне
 * остаётся компактная панель. Системное имя класса проверяется латиницей и
 * уникальностью сразу в форме; у класса появилось описание.
 */
final readonly class ClassesController extends Controller
{
    private const TABS = ['overview', 'properties', 'methods', 'objects'];

    private const FLASH_ERROR = 'classes.error';

    public function __construct(
        private Factory $view,
        private ClassCatalog $catalog,
        private ClassRepository $classes,
        private PropertyRepository $properties,
        private MethodRepository $methods,
        private ObjectRepository $objects,
        private PropertySubscriptionRepository $subscriptions,
        private RenameNotice $renames,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $data = $this->catalog->data();
        $data['tree']['query'] = $search;
        $data['tree']['storageKey'] = 'mydomo.classes.expanded.' . ($this->session->user()['id'] ?? 0);
        $content = $this->view->make('pages.classes.index', $data + [
            'search' => $search,
            'error' => $this->pullError(),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Классы', $content, 'classes'));
    }

    public function create(Request $request): Response
    {
        $parentId = max(0, (int)$request->query->get('parent_id', 0));

        return $this->form(null, null, $parentId > 0 ? ['parent_id' => $parentId] : null);
    }

    public function store(Request $request): Response
    {
        try {
            $id = $this->classes->create(
                (string)$request->request->get('title', ''),
                (int)$request->request->get('parent_id', 0),
                (string)$request->request->get('description', '')
            );
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), $request->request->all(), 422);
        }

        return $this->panel->redirect('/classes/' . $id . '/edit', 'Класс создан.');
    }

    public function edit(string $id, Request $request): Response
    {
        $class = $this->classes->find((int)$id);
        if ($class === null) {
            return $this->notFound();
        }

        $tab = (string)$request->query->get('tab', '');
        $tab = $tab === '' ? 'overview' : $tab;

        return $this->form($class, null, null, 200, in_array($tab, self::TABS, true) ? $tab : 'overview');
    }

    public function update(string $id, Request $request): Response
    {
        $class = $this->classes->find((int)$id);
        if ($class === null) {
            return new RedirectResponse('/classes');
        }

        try {
            $this->classes->update(
                (int)$id,
                (string)$request->request->get('title', ''),
                (int)$request->request->get('parent_id', 0),
                (string)$request->request->get('description', '')
            );
        } catch (InvalidArgumentException $error) {
            return $this->form($class, $error->getMessage(), $request->request->all(), 422, 'overview');
        }

        return $this->panel->redirect('/classes/' . $id . '/edit', $this->renames->message(
            (string)$class['title'],
            (string)$request->request->get('title', ''),
            'Класс сохранён.'
        ));
    }

    public function delete(string $id): Response
    {
        try {
            $this->classes->delete((int)$id);
        } catch (InvalidArgumentException $error) {
            $this->session->set(self::FLASH_ERROR, $error->getMessage());

            return new RedirectResponse('/classes/' . $id . '/edit');
        }

        return $this->panel->redirect(
            '/classes',
            'Класс удалён.'
        );
    }

    /**
     * @param array{id:int,title:string,parent_id:int,description:string}|null $class
     * @param array<string, mixed>|null $draft
     */
    private function form(
        ?array $class,
        ?string $error = null,
        ?array $draft = null,
        int $status = 200,
        string $tab = 'overview'
    ): Response {
        $classId = (int)($class['id'] ?? 0);
        $allClasses = $this->classes->all();
        $parentId = (int)($draft['parent_id'] ?? $class['parent_id'] ?? 0);
        $lineage = $classId > 0 ? $this->classes->lineage($classId) : [];
        $path = implode(' → ', array_map(
            static fn (array $row): string => (string)$row['title'],
            array_reverse($lineage)
        ));
        $children = $classId > 0 ? $this->classes->children($classId) : [];
        $excluded = $classId > 0 ? $this->classes->descendantIds($classId) : [];
        $parentOptions = array_values(array_filter(
            $allClasses,
            static fn (array $row): bool => !in_array((int)$row['id'], $excluded, true)
        ));

        $properties = $classId > 0 ? $this->properties->effectiveForClass($classId) : [];
        $methods = $classId > 0 ? $this->methods->effectiveForClass($classId) : [];
        $objects = $classId > 0 ? $this->objects->forClass($classId, false) : [];

        $content = $this->view->make('pages.classes.form', [
            'class' => $class,
            'draft' => $draft,
            'tab' => $class === null ? 'overview' : (in_array($tab, self::TABS, true) ? $tab : 'overview'),
            'classes' => $allClasses,
            'parentId' => $parentId,
            'parentOptions' => $parentOptions,
            'lineage' => $lineage,
            'path' => $path,
            'children' => $children,
            'properties' => $properties,
            'methods' => $methods,
            'objects' => $objects,
            'propertyBindings' => $this->subscriptions->forProperties(array_map('intval', array_column($properties, 'id'))),
            'takenNames' => $this->classes->titles($classId),
            'ownProperties' => count(array_filter($properties, static fn (array $row): bool => $row['origin'] === 'own')),
            'inheritedProperties' => count(array_filter($properties, static fn (array $row): bool => $row['origin'] === 'inherited')),
            'ownMethods' => count(array_filter($methods, static fn (array $row): bool => $row['origin'] === 'own')),
            'inheritedMethods' => count(array_filter($methods, static fn (array $row): bool => $row['origin'] === 'inherited')),
            'error' => $error ?? $this->pullError(),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(
            new PanelPage($class === null ? 'Новый класс' : 'Класс: ' . (string)$class['title'], $content, 'classes'),
            $status
        );
    }

    private function notFound(): Response
    {
        $content = $this->view->make('pages.objects.missing', [])->render();

        return $this->panel->render(new PanelPage('Класс не найден', $content, 'classes'), 404);
    }

    private function pullError(): ?string
    {
        $error = $this->session->get(self::FLASH_ERROR);
        if ($error === null) {
            return null;
        }
        $this->session->forget(self::FLASH_ERROR);

        return is_string($error) && $error !== '' ? $error : null;
    }

}
