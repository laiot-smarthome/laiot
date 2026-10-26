<?php

declare(strict_types=1);

namespace MajorDoMo\Modules\Core\Users;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
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
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string)$request->query->get('q', ''));
        $content = $this->view->make('users::index', [
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
            $this->users->create($username, $name, $password, $isAdmin);
        } catch (InvalidArgumentException $error) {
            return $this->form(null, $error->getMessage(), [
                'username' => $username,
                'name' => $name,
                'is_admin' => $isAdmin,
            ], 422);
        }

        return new RedirectResponse('/module/users');
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

        return new RedirectResponse('/module/users');
    }

    /**
     * @param array{id:int,username:string,name:string,is_admin:bool}|null $user
     * @param array{username:string,name:string,is_admin:bool}|null $draft
     */
    private function form(?array $user, ?string $error = null, ?array $draft = null, int $status = 200): Response
    {
        $content = $this->view->make('users::form', [
            'user' => $user,
            'draft' => $draft,
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage($user === null ? 'Новый пользователь' : 'Пользователь', $content, 'users'), $status);
    }
}
