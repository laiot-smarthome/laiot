<?php

declare(strict_types=1);

namespace LaIOT\Zigbee\Http;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LaIOT\Http\Controller;
use LaIOT\Http\PanelPage;
use LaIOT\Http\PanelRenderer;
use LaIOT\Http\Session\SessionStore;
use LaIOT\Services\Modules\ModuleManager;
use LaIOT\Zigbee\Repository;
use LaIOT\Zigbee\Service;
use LaIOT\Zigbee\Support\RuleForm;
use LaIOT\Zigbee\ValueTransformer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Привязка свойства Zigbee к объекту LaIOT и преобразования.
 *
 * Одна форма соединяет направление «в объект» (приём), «из объекта»
 * (отправка) и режим события. Тест преобразования выполняется отдельным POST
 * и ничего не сохраняет: значение и результат видны в Notify.
 *
 * 2026-10-28 — страница свойства вместо alpha `zigbee_devices_edit`.
 */
final readonly class PropertyController extends Controller
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

    public function show(int $id): Response
    {
        $property = $this->repository->propertyById($id);
        if ($property === null) {
            return $this->panel->redirect('/module/zigbee', 'Свойство устройства не найдено.', 'danger');
        }
        $device = $this->repository->deviceById((int)$property['device_id']);
        if ($device === null) {
            return $this->panel->redirect('/module/zigbee', 'Устройство не найдено.', 'danger');
        }
        $binding = $this->repository->binding($id);
        $incoming = ValueTransformer::decode((string)($binding['incoming'] ?? ''));
        $outgoing = ValueTransformer::decode((string)($binding['outgoing'] ?? ''));
        $content = $this->view->make('zigbee::property', [
            'property' => $property,
            'device' => $device,
            'bridge' => $this->repository->bridge((int)$device['bridge_id']),
            'binding' => $binding,
            'incoming' => is_array($incoming) ? RuleForm::fields($incoming) : RuleForm::fields(null),
            'outgoing' => is_array($outgoing) ? RuleForm::fields($outgoing) : RuleForm::fields(null),
            'expose' => $this->prettyExpose($property),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(
            new PanelPage('Zigbee · ' . (string)$property['path'], $content, 'zigbee')
        );
    }

    public function update(int $id, Request $request): Response
    {
        $url = '/module/zigbee/properties/' . $id;
        try {
            $this->service->saveBinding($id, [
                'linked_object' => (string)$request->input('linked_object', ''),
                'linked_property' => (string)$request->input('linked_property', ''),
                'linked_method' => (string)$request->input('linked_method', ''),
                'enabled' => $request->boolean('enabled'),
                'write_enabled' => $request->boolean('write_enabled'),
                'event_mode' => (string)$request->input('event_mode', 'always'),
                'incoming' => RuleForm::parse($this->ruleInput($request, 'incoming')),
                'outgoing' => RuleForm::parse($this->ruleInput($request, 'outgoing')),
            ], $this->modules->context('zigbee')->subscriptions());
        } catch (InvalidArgumentException $error) {
            return $this->panel->redirect($url, 'Привязка не сохранена: ' . $error->getMessage(), 'danger');
        }

        return $this->panel->redirect($url, 'Привязка сохранена.');
    }

    /**
     * Ручная команда устройству по текущему правилу отправки.
     */
    public function write(int $id, Request $request): Response
    {
        $url = '/module/zigbee/properties/' . $id;
        $value = trim((string)$request->input('value', ''));
        try {
            $this->service->write($id, $value);
        } catch (InvalidArgumentException $error) {
            return $this->panel->redirect($url, 'Команда не отправлена: ' . $error->getMessage(), 'danger');
        }

        return $this->panel->redirect($url, 'Команда поставлена в очередь MQTT.');
    }

    public function test(int $id, Request $request): Response
    {
        $url = '/module/zigbee/properties/' . $id;
        $direction = (string)$request->input('direction', 'incoming');
        $value = (string)$request->input('test_value', '');
        try {
            $result = $this->service->testConversion(
                $id,
                $direction,
                $value,
                RuleForm::parse($this->ruleInput($request, $direction)) ?? []
            );
        } catch (InvalidArgumentException $error) {
            return $this->panel->redirect($url, 'Проверка не выполнена: ' . $error->getMessage(), 'danger');
        }

        return $this->panel->redirect(
            $url,
            'Преобразование: ' . ValueTransformer::json($result['input'])
                . ' → ' . ValueTransformer::json($result['output'])
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleInput(Request $request, string $prefix): array
    {
        $rule = $request->input($prefix, []);

        return is_array($rule) ? $rule : [];
    }

    /**
     * @param array<string, mixed> $property
     */
    private function prettyExpose(array $property): string
    {
        $expose = ValueTransformer::decode((string)($property['expose'] ?? ''));
        if (!is_array($expose)) {
            return '';
        }

        return (string)json_encode($expose, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
