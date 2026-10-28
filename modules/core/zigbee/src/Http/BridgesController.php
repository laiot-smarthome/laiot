<?php

declare(strict_types=1);

namespace MajorDoMo\Zigbee\Http;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JsonException;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Zigbee\Repository;
use MajorDoMo\Zigbee\Service;
use Symfony\Component\HttpFoundation\Response;

/**
 * Экземпляры Zigbee2MQTT: корневые топики и команды bridge/request.
 *
 * Корень существующего экземпляра не меняется: смена корня — это другой
 * экземпляр, иначе привязки молча потеряли бы адрес. Команды — реальные
 * запросы Zigbee2MQTT; их ответы попадают в журнал модуля.
 *
 * 2026-10-28 — страница экземпляров вместо alpha `zigbee` вкладки.
 */
final readonly class BridgesController extends Controller
{
    public function __construct(
        private Factory $view,
        private PanelRenderer $panel,
        private Repository $repository,
        private Service $service,
        private ModuleManager $modules,
        private SessionStore $session
    ) {
    }

    public function index(Request $request): Response
    {
        $editId = max(0, (int)$request->query('edit', 0));
        $editing = $editId > 0 ? $this->repository->bridge($editId) : null;
        $content = $this->view->make('zigbee::bridges', [
            'bridges' => $this->repository->bridges(),
            'counts' => $this->repository->counts(),
            'editing' => $editing,
            'events' => $this->repository->events(['per_page' => 10]),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Zigbee · экземпляры', $content, 'zigbee'));
    }

    public function store(Request $request): Response
    {
        return $this->save(0, $request);
    }

    public function update(int $id, Request $request): Response
    {
        return $this->save($id, $request);
    }

    public function delete(int $id): Response
    {
        try {
            $this->service->deleteBridge($id, $this->modules->context('zigbee')->subscriptions());
        } catch (InvalidArgumentException $error) {
            return $this->panel->redirect('/module/zigbee/bridges', $error->getMessage(), 'danger');
        }

        return $this->panel->redirect('/module/zigbee/bridges', 'Экземпляр удалён.');
    }

    public function command(int $id, Request $request): Response
    {
        $command = (string)$request->input('command', '');
        $raw = trim((string)$request->input('params', ''));
        try {
            $params = $raw === '' ? [] : json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            return $this->panel->redirect(
                '/module/zigbee/bridges',
                'Параметры команды — JSON-объект: ' . $error->getMessage(),
                'danger'
            );
        }
        if (!is_array($params) || array_is_list($params)) {
            return $this->panel->redirect('/module/zigbee/bridges', 'Параметры команды — JSON-объект.', 'danger');
        }
        try {
            $this->service->command($id, $command, $params);
        } catch (InvalidArgumentException $error) {
            return $this->panel->redirect('/module/zigbee/bridges', 'Команда не отправлена: ' . $error->getMessage(), 'danger');
        }

        return $this->panel->redirect('/module/zigbee/bridges', 'Команда Zigbee2MQTT отправлена: ' . $command);
    }

    private function save(int $id, Request $request): Response
    {
        try {
            $saved = $this->repository->saveBridge([
                'title' => (string)$request->input('title', ''),
                'base_topic' => (string)$request->input('base_topic', ''),
                'enabled' => $request->boolean('enabled'),
            ], $id);
        } catch (InvalidArgumentException $error) {
            return $this->panel->redirect('/module/zigbee/bridges', $error->getMessage(), 'danger');
        }

        return $this->panel->redirect(
            '/module/zigbee/bridges',
            $id > 0 ? 'Экземпляр сохранён. Перезапустите worker MQTT, если корень новый.' : 'Экземпляр создан: ' . $saved
        );
    }
}
