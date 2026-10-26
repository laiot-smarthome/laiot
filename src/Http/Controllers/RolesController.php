<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use MajorDoMo\Auth\RoleRepository;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Справочник ролей и разрешений. Управление пользователями появится
 * отдельной страницей, но права уже видны администратору.
 */
final readonly class RolesController extends Controller
{
    public function __construct(
        private Factory $view,
        private RoleRepository $roles,
        private PanelRenderer $panel
    ) {
    }

    public function __invoke(): Response
    {
        $content = $this->view->make('pages.roles', [
            'roles' => $this->roles->allRoles(),
            'permissions' => $this->roles->allPermissions(),
        ])->render();

        return $this->panel->render(new PanelPage('Роли и права', $content, 'roles'));
    }
}
