<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Audit\ActionLogger;
use MajorDoMo\Auth\RoleRepository;
use MajorDoMo\Auth\UserRepository;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Пользователи MyDoMo: список, создание, редактирование, soft delete.
 */
final readonly class UsersController extends Controller
{
    public function __construct(
        private Factory $view,
        private UserRepository $users,
        private RoleRepository $roles,
        private SessionStore $session,
        private PanelRenderer $panel,
        private ActionLogger $audit
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $content = $this->view->make('pages.users.index', [
            'users' => $this->users->all($search),
            'search' => $search,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Пользователи', $content, 'users'));
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        $username = (string)$request->request->get('username', '');
        $name = (string)$request->request->get('name', '');
        $password = (string)$request->request->get('password', '');
        $isAdmin = (string)$request->request->get('is_admin', '') === '1';

        try {
            $id = $this->users->create($username, $name, $password, $isAdmin);
            $this->roles->syncUserRoles($id, $this->roleIds($request));
            $this->audit->log('user.create', 'Создан пользователь: ' . $username, (string)($this->session->user()['username'] ?? ''));
            return new RedirectResponse('/module/users/' . $id . '/edit');
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), [
                'username' => $username,
                'name' => $name,
                'is_admin' => $isAdmin,
            ], 422);
        }
    }

    public function edit(string $id): Response
    {
        $user = $this->users->find((int)$id);

        return $user === null
            ? $this->panel->render(new PanelPage('Пользователи', '<p class="md-alert md-alert--danger">Пользователь не найден.</p>', 'users'), 404)
            : $this->form($user);
    }

    public function update(string $id, Request $request): Response
    {
        $user = $this->users->find((int)$id);
        if ($user === null) {
            return new RedirectResponse('/module/users');
        }

        $username = (string)$request->request->get('username', '');
        $name = (string)$request->request->get('name', '');
        $password = (string)$request->request->get('password', '');
        $isAdmin = (string)$request->request->get('is_admin', '') === '1';

        try {
            $this->users->update((int)$id, $username, $name, $password, $isAdmin);
            $this->roles->syncUserRoles((int)$id, $this->roleIds($request));
            $this->audit->log('user.update', 'Изменён пользователь: ' . $username, (string)($this->session->user()['username'] ?? ''));
        } catch (InvalidArgumentException $error) {
            return $this->form($user, $error->getMessage(), [
                'username' => $username,
                'name' => $name,
                'is_admin' => $isAdmin,
            ], 422);
        }

        return new RedirectResponse('/module/users');
    }

    public function delete(string $id): Response
    {
        $this->users->delete((int)$id);
        $this->audit->log('user.delete', 'Удалён пользователь #' . $id, (string)($this->session->user()['username'] ?? ''));

        return new RedirectResponse('/module/users');
    }

    /**
     * @return list<int>
     */
    private function roleIds(Request $request): array
    {
        $ids = $request->request->all('role_ids');

        return array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
    }

    /**
     * @param array{id:int,username:string,name:string,is_admin:bool}|null $user
     * @param array{username:string,name:string,is_admin:bool}|null $draft
     */
    private function form(?array $user, ?string $error = null, ?array $draft = null, int $status = 200): Response
    {
        $content = $this->view->make('pages.users.form', [
            'user' => $user,
            'draft' => $draft,
            'roles' => $this->roles->allRoles(),
            'selectedRoles' => $user !== null ? $this->roles->roleIdsForUser($user['id']) : [],
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage($user === null ? 'Новый пользователь' : 'Пользователь', $content, 'users'), $status);
    }
}
