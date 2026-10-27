<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Audit\ActionLogger;
use MajorDoMo\Auth\RoleRepository;
use MajorDoMo\Auth\UserRepository;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Objects\ObjectRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Пользователи MyDoMo: список, создание, редактирование, soft delete.
 *
 * 2026-10-27 — единый раздел с ролями, объектом Users и состоянием присутствия;
 * ошибки сохраняют выбор ролей, собственный доступ нельзя снять случайно.
 */
final readonly class UsersController extends Controller
{
    public function __construct(
        private Factory $view,
        private UserRepository $users,
        private RoleRepository $roles,
        private ObjectRepository $objects,
        private DatabaseManager $database,
        private SessionStore $session,
        private PanelRenderer $panel,
        private ActionLogger $audit
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $users = $this->users->all($search);

        $content = $this->view->make('pages.users.index', [
            'users' => $users,
            'rolesByUser' => $this->roles->rolesByUser(),
            'presence' => $this->presence(),
            'search' => $search,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Пользователи', $content, 'users'));
    }

    public function create(): Response
    {
        $userRole = array_values(array_filter($this->roles->allRoles(), static fn (array $role): bool => $role['name'] === 'user'));

        return $this->form(null, null, ['role_ids' => array_column($userRole, 'id')]);
    }

    public function store(Request $request): Response
    {
        $username = (string)$request->request->get('username', '');
        $name = (string)$request->request->get('name', '');
        $password = (string)$request->request->get('password', '');
        $isAdmin = (string)$request->request->get('is_admin', '') === '1';

        try {
            $id = $this->users->create($username, $name, $password, $isAdmin, $this->roleIds($request));
            $this->audit->log('user.create', 'Создан пользователь: ' . $username, (string)($this->session->user()['username'] ?? ''));
            return new RedirectResponse('/users/' . $id . '/edit');
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), [
                'username' => $username,
                'name' => $name,
                'is_admin' => $isAdmin,
                'role_ids' => $request->request->all('role_ids'),
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
            return new RedirectResponse('/users');
        }

        $username = (string)$request->request->get('username', '');
        $name = (string)$request->request->get('name', '');
        $password = (string)$request->request->get('password', '');
        $isAdmin = (string)$request->request->get('is_admin', '') === '1';

        try {
            if ((int)$id === (int)($this->session->user()['id'] ?? 0) && !$isAdmin) {
                throw new InvalidArgumentException('Нельзя снять доступ в админку у собственной учётной записи.');
            }
            $this->users->update((int)$id, $username, $name, $password, $isAdmin, $this->roleIds($request));
            $this->audit->log('user.update', 'Изменён пользователь: ' . $username, (string)($this->session->user()['username'] ?? ''));
        } catch (InvalidArgumentException $error) {
            return $this->form($user, $error->getMessage(), [
                'username' => $username,
                'name' => $name,
                'is_admin' => $isAdmin,
                'role_ids' => $request->request->all('role_ids'),
            ], 422);
        }

        return new RedirectResponse('/users');
    }

    public function delete(string $id): Response
    {
        if ((int)$id === (int)($this->session->user()['id'] ?? 0)) {
            return $this->form($this->users->find((int)$id), 'Нельзя удалить собственную учётную запись.', null, 422);
        }
        $this->users->delete((int)$id);
        $this->audit->log('user.delete', 'Удалён пользователь #' . $id, (string)($this->session->user()['username'] ?? ''));

        return new RedirectResponse('/users');
    }

    /**
     * @return list<int>
     */
    private function roleIds(Request $request): array
    {
        $ids = $request->request->all('role_ids');

        foreach ($ids as $id) {
            if ((!is_int($id) && !is_string($id)) || preg_match('/^[1-9][0-9]*$/D', (string)$id) !== 1) {
                throw new InvalidArgumentException('Некорректный список ролей.');
            }
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * @param array{id:int,username:string,name:string,is_admin:bool,object_id:int}|null $user
     * @param array<string,mixed>|null $draft
     */
    private function form(?array $user, ?string $error = null, ?array $draft = null, int $status = 200): Response
    {
        $content = $this->view->make('pages.users.form', [
            'user' => $user,
            'draft' => $draft,
            'roles' => $this->roles->allRoles(),
            'selectedRoles' => $draft !== null && isset($draft['role_ids'])
                ? array_map('intval', array_filter($draft['role_ids'], 'is_scalar'))
                : ($user !== null ? $this->roles->roleIdsForUser($user['id']) : []),
            'permissions' => $this->roles->allPermissions(),
            'grants' => $this->roles->permissionsByRole(),
            'object' => $user !== null ? $this->objects->find($user['object_id']) : null,
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage($user === null ? 'Новый пользователь' : 'Пользователь', $content, 'users'), $status);
    }

    /** @return array<int,array<string,string>> */
    private function presence(): array
    {
        $rows = $this->database->connection()->table('users as u')
            ->join('property_values as v', 'v.object_id', '=', 'u.object_id')
            ->join('properties as p', 'p.id', '=', 'v.property_id')
            ->whereNull('u.deleted_at')->whereNull('p.deleted_at')->whereNull('v.deleted_at')
            ->whereIn('p.title', ['isHome', 'isWifiConnected'])->get(['u.id', 'p.title', 'v.value']);
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row->id][(string)$row->title] = (string)$row->value;
        }

        return $result;
    }
}
