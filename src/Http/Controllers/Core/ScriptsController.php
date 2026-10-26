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
use MajorDoMo\Scripts\ScriptRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Пользовательские скрипты: редактирование кода, без запуска циклов.
 */
final readonly class ScriptsController extends Controller
{
    public function __construct(
        private Factory $view,
        private ScriptRepository $scripts,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $categoryId = (int)$request->query->get('category_id', 0);
        $categories = $this->scripts->categories();
        $categoryNames = [];
        foreach ($categories as $category) {
            $categoryNames[$category['id']] = $category['title'];
        }

        $content = $this->view->make('pages.scripts.index', [
            'scripts' => $this->scripts->all($search, $categoryId),
            'categories' => $categories,
            'categoryNames' => $categoryNames,
            'categoryCounts' => $this->scripts->categoryCounts(),
            'search' => $search,
            'categoryId' => $categoryId,
            'message' => trim((string)$request->query->get('done', '')),
            'error' => trim((string)$request->query->get('error', '')),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Скрипты', $content, 'scripts'));
    }

    public function storeCategory(Request $request): Response
    {
        try {
            $this->scripts->createCategory((string)$request->request->get('title', ''));
        } catch (InvalidArgumentException $error) {
            return new RedirectResponse('/scripts?error=' . rawurlencode($error->getMessage()));
        }

        return new RedirectResponse('/scripts?done=' . rawurlencode('Категория создана.'));
    }

    public function renameCategory(string $id, Request $request): Response
    {
        try {
            $this->scripts->renameCategory((int)$id, (string)$request->request->get('title', ''));
        } catch (InvalidArgumentException $error) {
            return new RedirectResponse('/scripts?error=' . rawurlencode($error->getMessage()));
        }

        return new RedirectResponse('/scripts?done=' . rawurlencode('Категория переименована.'));
    }

    public function deleteCategory(string $id): Response
    {
        $this->scripts->deleteCategory((int)$id);

        return new RedirectResponse('/scripts?done=' . rawurlencode('Категория удалена, сценарии остались без категории.'));
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    /**
     * Экспорт сценариев в JSON: перенос между установками и резервная копия.
     */
    public function export(): Response
    {
        $rows = [];
        foreach ($this->scripts->all() as $script) {
            $full = $this->scripts->find($script['id']);
            if ($full === null) {
                continue;
            }
            $rows[] = [
                'title' => $full['title'],
                'description' => $full['description'],
                'code' => $full['code'],
                'category_id' => $full['category_id'],
                'run_periodically' => $full['run_periodically'],
            ];
        }

        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return new RedirectResponse('/scripts?error=' . rawurlencode('Не удалось сформировать JSON.'));
        }

        return new Response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="mydomo-scripts-' . date('Y-m-d') . '.json"',
        ]);
    }

    public function importForm(): Response
    {
        $content = $this->view->make('pages.scripts.import', [
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Импорт сценариев', $content, 'scripts'));
    }

    public function import(Request $request): Response
    {
        $payload = trim((string)$request->request->get('payload', ''));
        $decoded = json_decode($payload, true);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            return new RedirectResponse('/scripts/import?error=' . rawurlencode('Ожидается JSON-массив сценариев.'));
        }

        $created = 0;
        $skipped = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = trim((string)($item['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            if ($this->scripts->findByTitle($title) !== null) {
                $skipped[] = $title;
                continue;
            }

            $this->scripts->create(
                $title,
                (string)($item['code'] ?? ''),
                (string)($item['description'] ?? ''),
                (int)($item['category_id'] ?? 0),
                (bool)($item['run_periodically'] ?? false)
            );
            $created++;
        }

        $query = 'done=' . rawurlencode('Импортировано сценариев: ' . $created . '.');
        if ($skipped !== []) {
            $query .= '&error=' . rawurlencode('Пропущены (уже есть): ' . implode(', ', array_slice($skipped, 0, 5)));
        }

        return new RedirectResponse('/scripts?' . $query);
    }

    public function store(Request $request): Response
    {
        try {
            $id = $this->scripts->create(
                (string)$request->request->get('title', ''),
                (string)$request->request->get('code', ''),
                (string)$request->request->get('description', ''),
                (int)$request->request->get('category_id', 0),
                (string)$request->request->get('run_periodically', '') === '1'
            );
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse('/scripts/' . $id . '/edit');
    }

    public function edit(string $id): Response
    {
        $script = $this->scripts->find((int)$id);

        return $script === null
            ? $this->panel->render(new PanelPage('Скрипты', '<p class="md-alert md-alert--danger">Скрипт не найден.</p>', 'scripts'), 404)
            : $this->form($script);
    }

    public function update(string $id, Request $request): Response
    {
        $script = $this->scripts->find((int)$id);
        if ($script === null) {
            return new RedirectResponse('/scripts');
        }

        try {
            $this->scripts->update(
                (int)$id,
                (string)$request->request->get('title', ''),
                (string)$request->request->get('code', ''),
                (string)$request->request->get('description', ''),
                (int)$request->request->get('category_id', 0),
                (string)$request->request->get('run_periodically', '') === '1'
            );
        } catch (InvalidArgumentException $error) {
            return $this->form($script, $error->getMessage(), $request->request->all(), 422);
        }

        return new RedirectResponse('/scripts');
    }

    public function delete(string $id): Response
    {
        $this->scripts->delete((int)$id);

        return new RedirectResponse('/scripts');
    }

    /**
     * @param array{id:int,title:string,code:string,description:string,category_id:int,run_periodically:bool}|null $script
     * @param array<string, mixed>|null $draft
     */
    private function form(?array $script, ?string $error = null, ?array $draft = null, int $status = 200): Response
    {
        $content = $this->view->make('pages.scripts.form', [
            'script' => $script,
            'draft' => $draft,
            'categories' => $this->scripts->categories(),
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage($script === null ? 'Новый скрипт' : 'Скрипт', $content, 'scripts'), $status);
    }
}
