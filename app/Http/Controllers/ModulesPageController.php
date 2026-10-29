<?php

declare(strict_types=1);

namespace LaIOT\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LaIOT\Http\Controller;
use LaIOT\Http\PanelPage;
use LaIOT\Http\PanelRenderer;
use LaIOT\Http\Session\SessionStore;
use LaIOT\Services\Modules\ModuleInstaller;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Реестр SDK-модулей и управление их жизненным циклом.
 *
 * Страница повторяет рабочий сценарий alpha-модуля control_modules, но
 * работает через ModuleInstaller: установка, включение, отключение и удаление
 * идут теми же сервисами, что и CLI `module:*`. Core-разделы ядра (объекты,
 * сценарии, пользователи) модулями не являются и здесь не отображаются.
 *
 * 2026-10-28 — результаты действий передаются Notify через сессию.
 * 2026-10-26 — страница переведена с простого списка `ModuleManager::all()`
 * на реестр установленных модулей и действия enable/disable/install/remove.
 */
final readonly class ModulesPageController extends Controller
{
    public function __construct(
        private Factory $view,
        private ModuleInstaller $installer,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $content = $this->view->make('pages.modules', [
            'modules' => $this->sortedModules(),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Модули', $content, 'modules'));
    }

    public function enable(string $id): Response
    {
        return $this->run($id, 'enable', 'Модуль включён.');
    }

    public function disable(string $id): Response
    {
        return $this->run($id, 'disable', 'Модуль выключен.');
    }

    public function install(string $id): Response
    {
        return $this->run($id, 'install', 'Модуль установлен и включён.');
    }

    public function remove(string $id): Response
    {
        return $this->run($id, 'remove', 'Модуль удалён из реестра.');
    }

    private function run(string $id, string $action, string $success): Response
    {
        try {
            match ($action) {
                'enable' => $this->installer->enable($id),
                'disable' => $this->installer->disable($id),
                'install' => $this->installer->install($id),
                'remove' => $this->installer->remove($id),
                default => throw new InvalidArgumentException('Неизвестное действие.'),
            };
        } catch (InvalidArgumentException $error) {
            return $this->panel->redirect('/admin/modules', $error->getMessage(), 'danger');
        } catch (Throwable $error) {
            return $this->panel->redirect('/admin/modules', 'Не удалось выполнить действие: ' . $error->getMessage(), 'danger');
        }

        return $this->panel->redirect('/admin/modules', $success);
    }

    /**
     * Сначала ядро и включённые модули, затем выключенные и неустановленные.
     *
     * @return list<array<string, mixed>>
     */
    private function sortedModules(): array
    {
        $modules = $this->installer->list();
        usort($modules, static function (array $left, array $right): int {
            $leftWeight = ($left['core'] ?? false) ? 0 : (($left['installed'] ?? false) ? 1 : 3);
            $rightWeight = ($right['core'] ?? false) ? 0 : (($right['installed'] ?? false) ? 1 : 3);
            if ($leftWeight !== $rightWeight) {
                return $leftWeight <=> $rightWeight;
            }

            return strnatcasecmp((string)$left['name'], (string)$right['name']);
        });

        return $modules;
    }
}
