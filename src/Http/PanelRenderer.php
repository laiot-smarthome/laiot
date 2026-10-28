<?php

declare(strict_types=1);

namespace MajorDoMo\Http;

use Illuminate\Contracts\View\Factory;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Panel\CoreSections;
use MajorDoMo\Panel\PanelAttention;
use Symfony\Component\HttpFoundation\Response;

/**
 * Единственная точка сборки страницы в alpha-layout.
 *
 * Новый дизайн не создаётся: layout использует alpha-классы и admin.css.
 *
 * 2026-10-27 — меню собирается здесь: ключи раскрытия, счётчики внимания и
 * активная группа передаются в layout готовыми, шаблон не ищет разделы сам.
 */
final readonly class PanelRenderer
{
    public function __construct(
        private Factory $view,
        private SessionStore $session,
        private ModuleManager $modules,
        private PanelAttention $attention
    ) {
    }

    public function render(PanelPage $page, int $status = 200): Response
    {
        $menu = CoreSections::prepare(
            $page->menu !== [] ? $page->menu : $this->defaultMenu(),
            $this->attention->badges()
        );
        $active = CoreSections::activeKeys($menu, $page->active);
        $notice = $this->session->get('panel.notice');
        if ($notice !== null) {
            $this->session->forget('panel.notice');
        }
        $html = $this->view->make('layouts.alpha', [
            'title' => $page->title,
            'content' => $page->content,
            'active' => $page->active,
            'active_group' => $active['group'],
            'active_catalog' => $active['catalog'],
            'csrf' => $this->session->csrfToken(),
            'user' => $this->session->user(),
            'menu' => $menu,
            'notice' => is_string($notice) && $notice !== '' ? $notice : null,
        ])->render();

        return new Response($html, $status);
    }

    /**
     * Меню ядровых разделов плюс страницы SDK-модулей отдельной группой.
     *
     * @return list<array{id:string,label:string,icon:string,items:list<array<string,mixed>>}>
     */
    private function defaultMenu(): array
    {
        $menu = CoreSections::structure();
        $modules = $this->modules->menuItems();
        if ($modules !== []) {
            $menu[] = [
                'id' => 'integrations',
                'label' => 'Интеграции',
                'icon' => 'ti-puzzle',
                'items' => $modules,
            ];
        }

        return $menu;
    }
}
