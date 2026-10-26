<?php

declare(strict_types=1);

namespace MajorDoMo\Modules\Core\SystemErrors;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Logging\ErrorRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ошибки ядра в alpha-layout.
 */
final readonly class SystemErrorsController extends Controller
{
    public function __construct(
        private Factory $view,
        private ErrorRepository $errors,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $level = trim((string)$request->query->get('level', ''));
        $content = $this->view->make('system_errors::index', [
            'errors' => $this->errors->latest(200, $level),
            'level' => $level,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Ошибки', $content, 'system_errors'));
    }

    public function delete(string $id): Response
    {
        $this->errors->delete((int)$id);

        return new RedirectResponse('/module/system_errors');
    }
}
