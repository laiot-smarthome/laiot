<?php

declare(strict_types=1);

namespace MajorDoMo\Modules\Core\Api;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Scripts\ScriptRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Админка входящих webhook: список, создание, редактирование, секрет.
 */
final readonly class WebhookController extends Controller
{
    public function __construct(
        private Factory $view,
        private PanelRenderer $panel,
        private WebhookService $webhooks,
        private ScriptRepository $scripts,
        private SessionStore $session
    ) {
    }

    public function index(): Response
    {
        return $this->renderIndex();
    }

    public function create(): Response
    {
        return $this->renderForm(null, [], null);
    }

    public function store(Request $request): Response
    {
        try {
            $created = $this->webhooks->create($request->request->all());

            return $this->renderIndex('Webhook создан. Секрет показан один раз.', $created['secret']);
        } catch (InvalidArgumentException $error) {
            return $this->renderForm(null, $request->request->all(), $error->getMessage(), 422);
        }
    }

    public function edit(int $id): Response
    {
        $webhook = $this->webhooks->find($id);
        if ($webhook === null) {
            return $this->renderIndex('Webhook не найден.', null, 404);
        }

        return $this->renderForm($webhook, $webhook, null);
    }

    public function update(Request $request, int $id): Response
    {
        try {
            $this->webhooks->update($id, $request->request->all());

            return $this->renderIndex('Webhook сохранён.');
        } catch (InvalidArgumentException $error) {
            $webhook = $this->webhooks->find($id);

            return $this->renderForm($webhook, $request->request->all(), $error->getMessage(), 422);
        }
    }

    public function destroy(int $id): Response
    {
        try {
            $this->webhooks->delete($id);

            return $this->renderIndex('Webhook удалён.');
        } catch (InvalidArgumentException $error) {
            return $this->renderIndex($error->getMessage(), null, 404);
        }
    }

    public function rotate(int $id): Response
    {
        try {
            $secret = $this->webhooks->rotate($id);

            return $this->renderIndex('Секрет перевыпущен. Старый больше не работает.', $secret);
        } catch (InvalidArgumentException $error) {
            return $this->renderIndex($error->getMessage(), null, 404);
        }
    }

    private function renderIndex(string $message = '', ?string $newSecret = null, int $status = 200): Response
    {
        $content = $this->view->make('api::index', [
            'webhooks' => $this->webhooks->listing(),
            'message' => $message,
            'newSecret' => $newSecret,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('API', $content, 'api'), $status);
    }

    /**
     * @param array<string, mixed>|null $webhook
     * @param array<string, mixed> $draft
     */
    private function renderForm(?array $webhook, array $draft, ?string $error, int $status = 200): Response
    {
        $content = $this->view->make('api::form', [
            'webhook' => $webhook,
            'draft' => $draft,
            'error' => $error,
            'scripts' => $this->scripts->all(),
            'methods' => WebhookService::METHODS,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage($webhook === null ? 'Новый webhook' : 'Webhook', $content, 'api'), $status);
    }
}
