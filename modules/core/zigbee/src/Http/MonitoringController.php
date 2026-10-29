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
use LaIOT\Zigbee\HealthSettings;
use LaIOT\Zigbee\Repository;
use LaIOT\Zigbee\Support\RuleForm;
use Symfony\Component\HttpFoundation\Response;

/**
 * Глобальные настройки мониторинга Zigbee.
 *
 * Устройство может переопределить эти значения на своей карточке. Глобальные
 * настройки не дублируются в реестре SDK: core-модуль не имеет общего экрана
 * настроек, как и MQTT.
 *
 * 2026-10-28 — вкладка «Мониторинг» перенесена в отдельную страницу.
 */
final readonly class MonitoringController extends Controller
{
    public function __construct(
        private Factory $view,
        private PanelRenderer $panel,
        private Repository $repository,
        private SessionStore $session
    ) {
    }

    public function show(): Response
    {
        $settings = $this->repository->healthSettings();
        $content = $this->view->make('zigbee::monitoring', [
            'settings' => $settings,
            'defaults' => HealthSettings::defaults(),
            'points' => RuleForm::pointsText($settings),
            'counts' => $this->repository->counts(),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Zigbee · мониторинг', $content, 'zigbee'));
    }

    public function save(Request $request): Response
    {
        try {
            $this->repository->saveHealthSettings(RuleForm::health($request->input()));
        } catch (InvalidArgumentException $error) {
            return $this->panel->redirect('/module/zigbee/monitoring', 'Настройки не сохранены: ' . $error->getMessage(), 'danger');
        }

        return $this->panel->redirect('/module/zigbee/monitoring', 'Настройки мониторинга сохранены.');
    }
}
