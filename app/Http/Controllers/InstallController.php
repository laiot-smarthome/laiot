<?php

declare(strict_types=1);

namespace LaIOT\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use LaIOT\Http\Controller;
use LaIOT\Http\Session\SessionStore;
use LaIOT\Services\Install\InstallCheck;
use LaIOT\Services\Install\InstallException;
use LaIOT\Services\Install\InstallRequest;
use LaIOT\Services\Install\InstallService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Мастер первой установки `/install`.
 *
 * Шаги серверные, а не только визуальные: каждый шаг проверяет свои поля и
 * не пускает дальше, пока ошибка не исправлена. Черновик подключения живёт
 * в сессии, поэтому пароль базы не переписывается в скрытые поля HTML;
 * пароль администратора спрашивается на последнем шаге и сразу используется.
 *
 * Страница работает до появления `.env` и базы: bootstrap нового ядра не
 * открывает соединение, пока не задано имя базы. Доступ закрыт одноразовым
 * ключом из `var/install.key`; после установки маршрут отвечает 404.
 *
 * 2026-10-28 — пошаговый мастер вместо одной формы.
 * 2026-10-29 — подготовленное SSH-мастером подключение читается только после проверки ключа.
 * 2026-10-30 — успешная проверка сохраняет пароли в черновике до перерисовки формы.
 */
final readonly class InstallController extends Controller
{
    /** Черновик подключения между шагами; пароль не попадает в HTML. */
    public const DRAFT_KEY = 'laiot_install_draft';

    public function __construct(
        private Factory $view,
        private SessionStore $session,
        private InstallService $installer
    ) {
    }

    public function show(Request $request): Response
    {
        if ($this->installer->installed()) {
            throw new NotFoundHttpException();
        }

        if ($this->installer->key() === null) {
            return $this->render('unavailable', [], 503);
        }

        $provided = trim((string)$request->query->get('key', ''));
        if ($provided === '') {
            return $this->render('key', ['keyError' => null, 'token' => ''], 200);
        }
        if (!$this->installer->tokenMatches($provided)) {
            return $this->render('key', [
                'keyError' => 'Ключ не подошёл. Проверь, что скопировал строку целиком.',
                'token' => '',
            ], 403);
        }
        $draft = $this->draft();
        $step = (int)$request->query->get('step', 1);
        if ($step >= 3 && $draft === []) {
            $step = 1;
        }
        if ($step < 1 || $step > 4) {
            $step = 1;
        }

        return $this->renderStep($step, $provided, $draft, [], [], 200);
    }

    public function apply(Request $request): Response
    {
        if ($this->installer->installed()) {
            throw new NotFoundHttpException();
        }

        $token = trim((string)$request->request->get('key', ''));
        if (!$this->installer->tokenMatches($token)) {
            return $this->render('key', [
                'keyError' => 'Ключ не подходит или устарел. Обнови страницу установки.',
                'token' => '',
            ], 403);
        }

        return match ((string)$request->request->get('stage', '')) {
            'checks' => $this->renderStep(2, $token, [], [], [], 200),
            'connect' => $this->renderStep(3, $token, $this->draft(), [], [], 200),
            'connection' => $this->connection($request, $token),
            'install' => $this->install($request, $token),
            default => $this->renderStep(1, $token, [], [], [], 200),
        };
    }

    private function connection(Request $request, string $token): Response
    {
        $draft = $this->draft();
        $input = $request->request->all();
        // Пустое поле пароля означает «оставить сохранённый»: иначе пароль
        // пришлось бы возвращать в HTML или спрашивать повторно.
        foreach (['db_password', 'redis_password'] as $key) {
            if (trim((string)($input[$key] ?? '')) === '' && isset($draft[$key])) {
                $input[$key] = $draft[$key];
            }
        }
        $install = InstallRequest::fromArray($input);
        $errors = $this->installer->validateConnection($install);
        $values = $this->connectionValues($install, $draft !== []);

        if ($errors !== []) {
            return $this->renderStep(3, $token, $draft, $errors, [], 422, $values);
        }

        $preflight = $this->installer->preflight($install);
        $blocked = $this->blocked($preflight);
        if (!$blocked) {
            $this->storeDraft($install);
            $draft = $this->draft();
            $values = $this->connectionValues($install, true);
        }
        $action = (string)$request->request->get('action', '');
        if ($action === 'check' || $blocked) {
            $errors = $blocked ? ['global' => 'Без подключения установка не пойдёт. Проверь отмеченное ниже.'] : [];

            return $this->renderStep(
                3,
                $token,
                $draft,
                $errors,
                $preflight,
                $blocked ? 422 : 200,
                $values
            );
        }

        return $this->renderStep(4, $token, $draft, [], [], 200, $this->adminValues([]));
    }

    private function install(Request $request, string $token): Response
    {
        $draft = $this->draft();
        if ($draft === []) {
            return $this->renderStep(
                3,
                $token,
                [],
                ['global' => 'Сначала подключим базу — без неё установка не начнётся.'],
                [],
                422,
                $this->connectionValues(InstallRequest::fromArray(InstallRequest::formDefaults()), false)
            );
        }

        $admin = [
            'admin_login' => (string)$request->request->get('admin_login', ''),
            'admin_name' => (string)$request->request->get('admin_name', ''),
            'admin_password' => (string)$request->request->get('admin_password', ''),
            'timezone' => (string)$request->request->get('timezone', 'Europe/Moscow'),
        ];
        $install = InstallRequest::fromArray(array_merge($draft, $admin));

        $errors = $this->installer->validateAdmin(
            $install,
            (string)$request->request->get('admin_password_confirm', '')
        );
        if ($errors !== []) {
            return $this->renderStep(4, $token, $draft, $errors, [], 422, $this->adminValues($admin));
        }

        try {
            $report = $this->installer->apply($install);
        } catch (InstallException $error) {
            $field = $error->field() ?? 'global';
            $errors = [$field => $error->getMessage()];
            $connectionFailed = in_array($field, ['db_host', 'db_port', 'db_name', 'db_user', 'db_password', 'storage'], true);

            return $connectionFailed
                ? $this->renderStep(3, $token, $draft, $errors, [], 422, $this->connectionValues($install, true))
                : $this->renderStep(4, $token, $draft, $errors, [], 422, $this->adminValues($admin));
        }

        $this->session->forget(self::DRAFT_KEY);

        return $this->render('done', [
            'report' => $report,
            'login' => '/login',
        ], 200);
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, string> $errors
     * @param list<InstallCheck> $preflight
     * @param array<string, mixed> $values
     */
    private function renderStep(
        int $step,
        string $token,
        array $draft,
        array $errors,
        array $preflight,
        int $status,
        array $values = []
    ): Response {
        $checks = $step === 2 ? $this->installer->requirements() : [];
        if ($step === 3 && $values === []) {
            $source = $draft !== []
                ? InstallRequest::fromArray($draft)
                : InstallRequest::fromArray(InstallRequest::formDefaults());
            $values = $this->connectionValues($source, $draft !== []);
        }
        if ($step === 4 && $values === []) {
            $values = $this->adminValues([]);
        }

        return $this->render('form', [
            'step' => $step,
            'token' => $token,
            'csrf' => $this->session->csrfToken(),
            'checks' => array_map(static fn (InstallCheck $check): array => $check->toArray(), $checks),
            'preflight' => array_map(static fn (InstallCheck $check): array => $check->toArray(), $preflight),
            'errors' => $errors,
            'values' => $values,
            'blocked' => $this->blocked($checks) || $this->blocked($preflight),
            'draftExists' => $draft !== [],
            'summary' => $step === 4 ? [
                'db' => (string)($draft['db_name'] ?? ''),
                'cache' => (string)($draft['cache_driver'] ?? 'file'),
            ] : [],
        ], $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionValues(InstallRequest $request, bool $draftExists): array
    {
        return [
            'db_host' => $request->dbHost,
            'db_port' => $request->dbPort,
            'db_name' => $request->dbName,
            'db_user' => $request->dbUser,
            'db_password' => '',
            'db_create' => $request->dbCreate,
            'cache_driver' => $request->cacheDriver,
            'redis_host' => $request->redisHost,
            'redis_port' => $request->redisPort,
            'redis_database' => $request->redisDatabase,
            'redis_password' => '',
            'db_password_saved' => $draftExists && $request->dbPassword !== '',
            'redis_password_saved' => $draftExists && $request->redisPassword !== '',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function adminValues(array $input): array
    {
        return [
            'admin_login' => (string)($input['admin_login'] ?? 'sysadmin'),
            'admin_name' => (string)($input['admin_name'] ?? 'Администратор'),
            'timezone' => (string)($input['timezone'] ?? 'Europe/Moscow'),
        ];
    }

    /**
     * @param list<InstallCheck> $checks
     */
    private function blocked(array $checks): bool
    {
        return array_filter($checks, static fn (InstallCheck $check): bool => $check->failed()) !== [];
    }

    /**
     * @return array<string, mixed>
     */
    private function draft(): array
    {
        $draft = $this->session->get(self::DRAFT_KEY);
        if ((!is_array($draft) || $draft === []) && ($initial = $this->installer->initialConnection()) !== null) {
            $this->storeDraft($initial);
            $draft = $this->session->get(self::DRAFT_KEY);
        }

        return is_array($draft) ? $draft : [];
    }

    private function storeDraft(InstallRequest $request): void
    {
        $this->session->set(self::DRAFT_KEY, [
            'db_host' => $request->dbHost,
            'db_port' => $request->dbPort,
            'db_name' => $request->dbName,
            'db_user' => $request->dbUser,
            'db_password' => $request->dbPassword,
            'db_create' => $request->dbCreate,
            'cache_driver' => $request->cacheDriver,
            'redis_host' => $request->redisHost,
            'redis_port' => $request->redisPort,
            'redis_database' => $request->redisDatabase,
            'redis_password' => $request->redisPassword,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $state, array $data, int $status): Response
    {
        $html = $this->view->make('install.page', $data + [
            'state' => $state,
            'version' => $this->installer->version(),
        ])->render();

        return $this->html($html, $status);
    }
}
