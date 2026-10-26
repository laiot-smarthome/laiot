<?php

declare(strict_types=1);

namespace MajorDoMo\Http;

use Illuminate\Contracts\View\Factory;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Modules\ModuleManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Единственная точка сборки страницы в alpha-layout.
 *
 * Новый дизайн не создаётся: layout использует alpha-классы и admin.css.
 */
final readonly class PanelRenderer
{
    public function __construct(
        private Factory $view,
        private SessionStore $session,
        private ModuleManager $modules
    ) {
    }

    public function render(PanelPage $page, int $status = 200): Response
    {
        $menu = $page->menu !== [] ? $page->menu : $this->defaultMenu($page->active);
        $html = $this->view->make('layouts.alpha', [
            'title' => $page->title,
            'content' => $page->content,
            'active' => $page->active,
            'csrf' => $this->session->csrfToken(),
            'user' => $this->session->user(),
            'menu' => $menu,
        ])->render();

        return new Response($html, $status);
    }

    /**
     * @return list<array{label:string,items:list<array{id:string,label:string,href:string,icon:string}>}>
     */
    private function defaultMenu(string $active): array
    {
        $items = [
            ['id' => 'dashboard', 'label' => 'Обзор', 'href' => '/admin', 'icon' => 'ti-layout-dashboard'],
            ['id' => 'status', 'label' => 'Статус', 'href' => '/status', 'icon' => 'ti-activity'],
            ['id' => 'roles', 'label' => 'Роли', 'href' => '/roles', 'icon' => 'ti-shield'],
            ['id' => 'tokens', 'label' => 'API-токены', 'href' => '/admin/tokens', 'icon' => 'ti-key'],
            ['id' => 'profile', 'label' => 'Профиль', 'href' => '/admin/profile', 'icon' => 'ti-user-circle'],
            ['id' => 'maintenance', 'label' => 'Обслуживание', 'href' => '/admin/maintenance', 'icon' => 'ti-tool'],
        ];
        foreach ($this->modules->menuItems() as $item) {
            $items[] = $item;
        }

        return [['label' => 'Система', 'items' => $items]];
    }
}
