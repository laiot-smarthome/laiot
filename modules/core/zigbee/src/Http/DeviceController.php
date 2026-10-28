<?php

declare(strict_types=1);

namespace MajorDoMo\Zigbee\Http;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Locations\LocationRepository;
use MajorDoMo\Modules\ModuleManager;
use MajorDoMo\Zigbee\HealthSettings;
use MajorDoMo\Zigbee\Repository;
use MajorDoMo\Zigbee\Service;
use MajorDoMo\Zigbee\Support\PanelData;
use MajorDoMo\Zigbee\Support\RuleForm;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Карточка устройства Zigbee: название, комната, свойства и переопределение
 * мониторинга.
 *
 * Привязки свойств редактируются на отдельной странице свойства: у одного
 * устройства их десятки, и смешивать их с настройками самого устройства в
 * одной форме неудобно.
 *
 * 2026-10-28 — карточка вместо alpha `zigbee_devices_edit`.
 */
final readonly class DeviceController extends Controller
{
    public function __construct(
        private Factory $view,
        private PanelRenderer $panel,
        private Repository $repository,
        private Service $service,
        private LocationRepository $locations,
        private ModuleManager $modules,
        private SessionStore $session
    ) {
    }

    public function show(int $id): Response
    {
        $device = $this->repository->deviceById($id);
        if ($device === null) {
            return $this->panel->redirect('/module/zigbee', 'Устройство не найдено.', 'danger');
        }
        $bridge = $this->repository->bridge((int)$device['bridge_id']);
        $global = $this->repository->healthSettings();
        $content = $this->view->make('zigbee::device', [
            'device' => $device,
            'bridge' => $bridge,
            'properties' => $this->repository->propertiesWithBindings($id),
            'rooms' => PanelData::rooms($this->locations),
            'health' => trim((string)($device['health_config'] ?? '')) === ''
                ? $global
                : $this->repository->healthSettings($device),
            'healthInherited' => trim((string)($device['health_config'] ?? '')) === '',
            'healthDefaults' => HealthSettings::defaults(),
            'state' => $this->repository->healthState('device:' . $id . ':availability'),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(
            new PanelPage('Zigbee · ' . $this->title($device), $content, 'zigbee')
        );
    }

    public function update(int $id, Request $request): Response
    {
        try {
            $this->service->updateDevice(
                $id,
                (string)$request->input('title', ''),
                max(0, (int)$request->input('location_id', 0))
            );
        } catch (InvalidArgumentException $error) {
            return $this->panel->redirect('/module/zigbee/devices/' . $id, $error->getMessage(), 'danger');
        }

        return $this->panel->redirect('/module/zigbee/devices/' . $id, 'Устройство сохранено.');
    }

    public function delete(int $id): Response
    {
        try {
            $this->service->deleteDevice($id, $this->modules->context('zigbee')->subscriptions());
        } catch (InvalidArgumentException $error) {
            return $this->panel->redirect('/module/zigbee', $error->getMessage(), 'danger');
        }

        return $this->panel->redirect('/module/zigbee', 'Устройство удалено.');
    }

    public function saveHealth(int $id, Request $request): Response
    {
        try {
            $settings = RuleForm::health($request->input());
            $this->repository->saveHealthSettings(
                $settings,
                $id,
                $request->boolean('inherit')
            );
        } catch (InvalidArgumentException $error) {
            return $this->panel->redirect(
                '/module/zigbee/devices/' . $id . '#monitoring',
                'Мониторинг не сохранён: ' . $error->getMessage(),
                'danger'
            );
        }

        return $this->panel->redirect('/module/zigbee/devices/' . $id . '#monitoring', 'Мониторинг устройства сохранён.');
    }

    public function inheritHealth(int $id): Response
    {
        try {
            $this->repository->saveHealthSettings(HealthSettings::defaults(), $id, true);
        } catch (Throwable $error) {
            return $this->panel->redirect('/module/zigbee/devices/' . $id, $error->getMessage(), 'danger');
        }

        return $this->panel->redirect(
            '/module/zigbee/devices/' . $id . '#monitoring',
            'Устройство снова использует общие настройки мониторинга.'
        );
    }

    /**
     * @param array<string, mixed> $device
     */
    private function title(array $device): string
    {
        $title = trim((string)($device['title'] ?? ''));

        return $title !== '' ? $title : (string)$device['friendly_name'];
    }
}
