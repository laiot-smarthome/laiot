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
use MajorDoMo\Properties\PropertyRepository;
use MajorDoMo\Properties\PropertySubscriptionRepository;
use MajorDoMo\Rename\NameReferenceUpdater;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Единый раздел «Классы»: дерево классов с подклассами, карточка класса,
 * его объекты, свойства и методы.
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
        private ClassRepository $classes,
        private PropertyRepository $properties,
        private MethodRepository $methods,
        private ObjectRepository $objects,
        private PropertySubscriptionRepository $subscriptions,
        private NameReferenceUpdater $references,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $allClasses = $this->classes->all();
        $rows = $this->classes->flattened();
        $objectsByClass = $this->safeObjects();
        $propertyCounts = $this->safeCount(fn (): array => $this->properties->effectiveCounts($allClasses));
        $methodCounts = $this->safeCount(fn (): array => $this->methods->effectiveCounts($allClasses));
        $objectCounts = $this->safeCount(fn (): array => $this->objects->countsByClass());

        $childrenByParent = [];
        foreach ($allClasses as $class) {
            $parentId = (int)$class['parent_id'];
            if ($parentId > 0) {
                $childrenByParent[$parentId][] = (int)$class['id'];
            }
        }
        $subtreeObjectCounts = [];
        $visiting = [];
        $subtree = function (int $classId) use (
            &$subtree,
            &$subtreeObjectCounts,
            &$visiting,
            $childrenByParent,
            $objectCounts
        ): int {
            if (isset($subtreeObjectCounts[$classId])) {
                return $subtreeObjectCounts[$classId];
            }
            if (isset($visiting[$classId])) {
                return 0;
            }
            $visiting[$classId] = true;
            $total = (int)($objectCounts[$classId] ?? 0);
            foreach ($childrenByParent[$classId] ?? [] as $childId) {
                $total += $subtree($childId);
            }
            unset($visiting[$classId]);

            return $subtreeObjectCounts[$classId] = $total;
        };
        foreach ($allClasses as $class) {
            $subtree((int)$class['id']);
        }

        $treeClasses = [];
        $treeObjects = [];
        foreach ($rows as $row) {
            $classId = (int)$row['id'];
            $classObjects = $objectsByClass[$classId] ?? [];
            $treeClasses[] = [
                'id' => $classId,
                'parent_id' => (int)$row['parent_id'],
                'depth' => (int)$row['depth'],
                'title' => (string)$row['title'],
                'description' => (string)$row['description'],
                'path' => (string)$row['path'],
                'has_children' => (bool)$row['has_children'],
                'children_count' => (int)$row['children_count'],
                'objects_count' => count($classObjects),
                'objects_deep_count' => (int)($subtreeObjectCounts[$classId] ?? 0),
            ];
            $treeObjects[(string)$classId] = array_map(
                static fn (array $object): array => [
                    'id' => (int)$object['id'],
                    'title' => (string)$object['title'],
                    'description' => (string)$object['description'],
                    'location_id' => (int)$object['location_id'],
                    'location_title' => (string)$object['location_title'],
                ],
                $classObjects
            );
        }

        $content = $this->view->make('pages.classes.index', [
            'rows' => $rows,
            'classes' => $allClasses,
            'objectsByClass' => $objectsByClass,
            'propertyCounts' => $propertyCounts,
            'methodCounts' => $methodCounts,
            'objectCounts' => $objectCounts,
            'subtreeObjectCounts' => $subtreeObjectCounts,
            'tree' => [
                'query' => $search,
                'classes' => $treeClasses,
                'objects' => (object)$treeObjects,
            ],
            'totalObjects' => array_sum($objectCounts),
            'totalProperties' => array_sum(array_column($propertyCounts, 'own')),
            'totalMethods' => array_sum(array_column($methodCounts, 'own')),
            'totalSubclasses' => count(array_filter($allClasses, static fn (array $class): bool => (int)$class['parent_id'] > 0)),
            'rootClasses' => count(array_filter($allClasses, static fn (array $class): bool => (int)$class['parent_id'] === 0)),
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

        return new RedirectResponse('/classes/' . $id . '/edit');
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

        $this->rememberRename((string)$class['title'], (string)$request->request->get('title', ''));

        return new RedirectResponse('/classes/' . $id . '/edit');
    }

    public function delete(string $id): Response
    {
        try {
            $this->classes->delete((int)$id);
        } catch (InvalidArgumentException $error) {
            $this->session->set(self::FLASH_ERROR, $error->getMessage());

            return new RedirectResponse('/classes/' . $id . '/edit');
        }

        return new RedirectResponse('/classes');
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
            'propertyBindings' => $this->safeBindings(array_column($properties, 'id')),
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

    /**
     * @return array<int,list<array{id:int,title:string,location_id:int,location_title:string,description:string}>>
     */
    private function safeObjects(): array
    {
        try {
            return $this->objects->groupedByClass();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param callable(): array<int,mixed> $callback
     * @return array<int,mixed>
     */
    private function safeCount(callable $callback): array
    {
        try {
            return $callback();
        } catch (Throwable) {
            return [];
        }
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

    /**
     * @param list<int> $propertyIds
     * @return array<int,list<array{module:string,object_id:int,object_title:string}>>
     */
    private function safeBindings(array $propertyIds): array
    {
        try {
            return $this->subscriptions->forProperties(array_map('intval', $propertyIds));
        } catch (Throwable) {
            return [];
        }
    }

    private function rememberRename(string $old, string $new): void
    {
        $old = trim($old);
        $new = trim($new);
        if ($old === '' || $old === $new) {
            return;
        }
        $message = 'Имя «' . $old . '» изменено на «' . $new . '». Обновлено ссылок: '
            . $this->references->changes() . '.';
        $warnings = $this->references->warnings();
        if ($warnings !== []) {
            $message .= ' Не удалось обновить файлов модулей: ' . count($warnings) . ' (подробности в логе).';
        }
        $this->session->set('panel.notice', $message);
    }
}
