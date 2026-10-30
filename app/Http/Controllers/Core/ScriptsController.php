<?php

declare(strict_types=1);

namespace LaIOT\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LaIOT\Http\Controller;
use LaIOT\Http\PanelPage;
use LaIOT\Http\PanelRenderer;
use LaIOT\Http\Session\SessionStore;
use LaIOT\Services\Runtime\InvalidUserCode;
use LaIOT\Services\Runtime\PhpSyntaxValidator;
use LaIOT\Services\Scripts\ScriptExecutor;
use LaIOT\Services\Scripts\ScriptRepository;
use LaIOT\Services\Scripts\ScriptTriggers;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Пользовательские скрипты: код, запуск по событию и ручная проверка.
 *
 * Категории сценариев управляются модальным Yoyo-компонентом `script-categories`
 * на странице списка: отдельной страницы и форм на главной больше нет.
 *
 * Изменения:
 * 2026-10-30 — ручной запуск подписывается названием сценария в X-ray.
 * 2026-10-29 — создание, сохранение и удаление подтверждаются через Notify.
 * 2026-10-28 — результаты операций передаются Notify без текста в URL.
 * 2026-10-27 — кнопка «Выполнить» и выбор окружения с ядром/без ядра;
 *              синхронный запуск отпускает блокировку сессии.
 * 2026-10-27 — категории вынесены в модалку, вместо расписания — системное событие;
 *              код проверяется синтаксически до сохранения.
 */
final readonly class ScriptsController extends Controller
{
    public function __construct(
        private Factory $view,
        private ScriptRepository $scripts,
        private ScriptExecutor $executor,
        private PhpSyntaxValidator $syntax,
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

        $scripts = [];
        foreach ($this->scripts->all($search, $categoryId) as $script) {
            $scripts[] = $script + ['trigger' => ScriptTriggers::label($script['event_name'])];
        }

        $content = $this->view->make('pages.scripts.index', [
            'scripts' => $scripts,
            'categories' => $categories,
            'categoryNames' => $categoryNames,
            'categoryCounts' => $this->scripts->categoryCounts(),
            'search' => $search,
            'categoryId' => $categoryId,


            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Скрипты', $content, 'scripts'));
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
                'event_name' => $full['event_name'],
                'use_core' => $full['use_core'],
            ];
        }

        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return $this->panel->redirect('/scripts', 'Не удалось сформировать JSON.', 'danger');
        }

        return new Response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="laiot-scripts-' . date('Y-m-d') . '.json"',
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
            return $this->panel->redirect('/scripts/import', 'Ожидается JSON-массив сценариев.', 'danger');
        }

        $created = 0;
        $skipped = [];
        $invalid = [];
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

            try {
                $this->scripts->create(
                    $title,
                    (string)($item['code'] ?? ''),
                    (string)($item['description'] ?? ''),
                    (int)($item['category_id'] ?? 0),
                    (string)($item['event_name'] ?? ''),
                    !array_key_exists('use_core', $item) || (bool)$item['use_core']
                );
            } catch (InvalidUserCode $error) {
                $invalid[] = $title . ' (строка ' . $error->codeLine . ': ' . $error->getMessage() . ')';
                continue;
            } catch (InvalidArgumentException $error) {
                $invalid[] = $title . ' (' . $error->getMessage() . ')';
                continue;
            }
            $created++;
        }

        $this->panel->notify('Импортировано сценариев: ' . $created . '.');
        $problems = [];
        if ($invalid !== []) {
            $problems[] = 'Не сохранены: ' . implode('; ', array_slice($invalid, 0, 5));
        }
        if ($skipped !== []) {
            $problems[] = 'Пропущены (уже есть): ' . implode(', ', array_slice($skipped, 0, 5));
        }
        if ($problems !== []) {
            $this->panel->notify(implode(' ', $problems), 'warning');
        }

        return new RedirectResponse('/scripts');
    }

    public function store(Request $request): Response
    {
        try {
            $id = $this->scripts->create(
                (string)$request->request->get('title', ''),
                (string)$request->request->get('code', ''),
                (string)$request->request->get('description', ''),
                (int)$request->request->get('category_id', 0),
                (string)$request->request->get('event_name', ''),
                $this->useCore($request)
            );
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $this->errorText($error), $request->request->all(), 422, $this->codeError($error));
        }

        return $this->panel->redirect('/scripts/' . $id . '/edit', 'Сценарий создан.');
    }

    /**
     * Ручной запуск черновика: синхронно, без сохранения.
     *
     * Код проверяется тем же валидатором, что и сохранение, но в базу ничего
     * не пишется. Результат, вывод и ошибка возвращаются в ту же форму.
     */
    public function run(Request $request): Response
    {
        $id = max(0, (int)$request->request->get('id', 0));
        $script = $id > 0 ? $this->scripts->find($id) : null;
        $code = (string)$request->request->get('code', '');

        $syntaxError = $this->syntax->validate($code);
        if ($syntaxError !== null) {
            return $this->form(
                $script,
                'Синтаксическая ошибка в строке ' . $syntaxError['line'] . ': ' . $syntaxError['message'],
                $request->request->all(),
                422,
                ['line' => $syntaxError['line'], 'message' => $syntaxError['message']]
            );
        }

        // Пока пользовательский код выполняется, файловая блокировка сессии
        // не нужна: иначе вкладки той же сессии ждут запуск, а не работают
        // параллельно. CSRF-токен к этому моменту уже проверен и прочитан.
        $this->session->releaseLock();
        $result = $this->executor->execute($code, [], $this->useCore($request), (string)($script['title'] ?? 'Редактор сценария'));

        return $this->form($script, null, $request->request->all(), 200, null, $result);
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
                (string)$request->request->get('event_name', ''),
                $this->useCore($request)
            );
        } catch (InvalidArgumentException $error) {
            return $this->form($script, $this->errorText($error), $request->request->all(), 422, $this->codeError($error));
        }

        return $this->panel->redirect('/scripts', 'Сценарий сохранён.');
    }

    public function delete(string $id): Response
    {
        $this->scripts->delete((int)$id);

        return $this->panel->redirect('/scripts', 'Сценарий удалён.');
    }

    /**
     * @param array{id:int,title:string,code:string,description:string,category_id:int,event_name:string,use_core:bool}|null $script
     * @param array<string, mixed>|null $draft
     * @param array{line:int,message:string}|null $codeError
     * @param array{ok:bool,output:string,result:string,error:string,duration_ms:int}|null $runResult
     */
    private function form(
        ?array $script,
        ?string $error = null,
        ?array $draft = null,
        int $status = 200,
        ?array $codeError = null,
        ?array $runResult = null
    ): Response {
        $content = $this->view->make('pages.scripts.form', [
            'script' => $script,
            'draft' => $draft,
            'categories' => $this->scripts->categories(),
            'triggers' => ScriptTriggers::catalog(),
            'error' => $error,
            'codeError' => $codeError,
            'runResult' => $runResult,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage($script === null ? 'Новый скрипт' : 'Скрипт', $content, 'scripts'), $status);
    }

    /**
     * Флаг «подключать ядро»; отсутствие поля считается включённым.
     */
    private function useCore(Request $request): bool
    {
        if (!$request->request->has('use_core')) {
            return true;
        }

        return (string)$request->request->get('use_core', '') === '1';
    }

    /**
     * Текст ошибки для общего оповещения: у синтаксической ошибки нужна строка.
     */
    private function errorText(InvalidArgumentException $error): string
    {
        if ($error instanceof InvalidUserCode) {
            return 'Синтаксическая ошибка в строке ' . $error->codeLine . ': ' . $error->getMessage();
        }

        return $error->getMessage();
    }

    /**
     * @return array{line:int,message:string}|null
     */
    private function codeError(InvalidArgumentException $error): ?array
    {
        return $error instanceof InvalidUserCode
            ? ['line' => $error->codeLine, 'message' => $error->getMessage()]
            : null;
    }
}
