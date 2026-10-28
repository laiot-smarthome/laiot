<?php

declare(strict_types=1);

namespace MajorDoMo\Mqtt\Http;

use Illuminate\Contracts\View\Factory;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Locations\LocationRepository;
use MajorDoMo\Mqtt\TopicRepository;
use MajorDoMo\Mqtt\TopicService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Карточка топика MQTT: привязка, направление, история и ручная команда.
 *
 * Форма сохраняется обычным POST с CSRF (это изменяющая операция, а не
 * состояние UI). Список топиков справа/ниже обновляет Yoyo-компонент.
 *
 * 2026-10-27 — редактор топика вместо alpha `edit_mqtt`.
 */
final readonly class TopicController extends Controller
{
    public function __construct(
        private Factory $view,
        private PanelRenderer $panel,
        private TopicRepository $topics,
        private TopicService $service,
        private LocationRepository $locations,
        private SessionStore $session
    ) {
    }

    public function create(): Response
    {
        return $this->form([], 0, '', '');
    }

    public function edit(int $id, Request $request): Response
    {
        $topic = $this->topics->find($id);
        if ($topic === null) {
            return new RedirectResponse('/module/mqtt?error=' . rawurlencode('Топик не найден.'));
        }

        return $this->form($topic, $id, '', trim((string)$request->query->get('done', '')));
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
        $this->service->delete($id);

        return new RedirectResponse('/module/mqtt?done=' . rawurlencode('Топик удалён.'));
    }

    public function publish(int $id, Request $request): Response
    {
        $value = (string)$request->input('value', '');
        try {
            $this->service->publishTest($id, $value);
        } catch (Throwable $error) {
            return new RedirectResponse(
                '/module/mqtt/topics/' . $id . '/edit?error=' . rawurlencode('Команда не отправлена: ' . $error->getMessage())
            );
        }

        return new RedirectResponse('/module/mqtt/topics/' . $id . '/edit?done=' . rawurlencode('Команда отправлена.'));
    }

    private function save(int $id, Request $request): Response
    {
        $input = $this->input($request);
        try {
            $saved = $this->service->save($input, $id);
        } catch (InvalidArgumentException $error) {
            return $this->form($input, $id, $error->getMessage(), '');
        } catch (QueryException $error) {
            // Уникальный путь мог занять параллельный запрос: показываем
            // понятное сообщение, а не страницу 500.
            return $this->form($input, $id, 'Топик не сохранён: путь уже занят или данные некорректны.', '');
        }

        return new RedirectResponse('/module/mqtt/topics/' . (int)$saved['id'] . '/edit?done=' . rawurlencode('Топик сохранён.'));
    }

    /**
     * @param array<string, mixed> $topic
     */
    private function form(array $topic, int $id, string $error, string $done): Response
    {
        $locations = [];
        try {
            foreach ($this->locations->all() as $location) {
                $locations[] = ['id' => (int)$location['id'], 'title' => (string)$location['title']];
            }
        } catch (Throwable) {
            $locations = [];
        }

        $content = $this->view->make('mqtt::topic', [
            'topic' => $topic,
            'id' => $id,
            'isNew' => $id === 0,
            'error' => $error,
            'done' => $done,
            'history' => $id > 0 ? $this->topics->history($id, 20) : [],
            'children' => $id > 0 ? $this->topics->children((string)($topic['path'] ?? ''), 50) : [],
            'locations' => $locations,
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(
            new PanelPage('MQTT · ' . (string)($topic['title'] ?? 'Новый топик'), $content, 'mqtt'),
            $error === '' ? 200 : 422
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function input(Request $request): array
    {
        return [
            'path' => (string)$request->input('path', ''),
            'title' => (string)$request->input('title', ''),
            'path_write' => (string)$request->input('path_write', ''),
            'linked_object' => (string)$request->input('linked_object', ''),
            'linked_property' => (string)$request->input('linked_property', ''),
            'linked_method' => (string)$request->input('linked_method', ''),
            'location_id' => (int)$request->input('location_id', 0),
            'logging' => $request->boolean('logging'),
            'only_new_value' => $request->boolean('only_new_value'),
            'readonly' => $request->boolean('readonly'),
            'replace_list' => (string)$request->input('replace_list', ''),
            'qos' => (int)$request->input('qos', 0),
            'retain' => $request->boolean('retain'),
        ];
    }
}
