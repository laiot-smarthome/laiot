<?php

declare(strict_types=1);

namespace LaIOT\Mqtt\Yoyo;

use Clickfwd\Yoyo\Component;
use LaIOT\Services\Auth\Authorization;
use LaIOT\Services\Events\PropertyEventQueue;
use LaIOT\Mqtt\RuntimeState;
use LaIOT\Services\Modules\ModuleManager;
use LaIOT\Mqtt\Settings;
use LaIOT\Services\Queue\Queue;

/**
 * Карточка состояния MQTT-цикла.
 *
 * Показывает, запущен ли worker и держит ли он соединение, счётчики приёма
 * и отправки, длину очереди команд и последнюю ошибку. Свёрнута по
 * умолчанию: в шапке остаются только признаки состояния, подробности — по
 * кнопке. Обновляется тем же событием `mqtt-refresh`, что и список топиков;
 * состояние «развёрнуто» переживает фоновое обновление, потому что это
 * публичное свойство компонента.
 *
 * 2026-10-27 — статус цикла MQTT как Yoyo-компонент.
 * 2026-10-27 — карточка сворачивается и обновляется в фоне.
 */
final class Status extends Component
{
    public string $open = '0';

    /** @var list<string> */
    protected $props = ['open'];

    /** @var array<string, string> */
    protected $listeners = ['mqtt-refresh' => 'refreshStatus'];

    private ?RuntimeState $runtime = null;

    private ?Settings $settings = null;

    private ?Queue $queue = null;

    private ?PropertyEventQueue $events = null;

    private bool $enabled = true;

    public function mount(
        RuntimeState $runtime,
        Settings $settings,
        Queue $queue,
        PropertyEventQueue $events,
        ModuleManager $modules,
        Authorization $authorization
    ): void {
        $this->enabled = $modules->get('mqtt') !== null && $authorization->can('settings.manage');
        $this->runtime = $runtime;
        $this->settings = $settings;
        $this->queue = $queue;
        $this->events = $events;
    }

    public function refreshStatus(): void
    {
        // Повторный render читает свежий снимок CacheStore.
    }

    public function toggle(): void
    {
        $this->open = $this->open === '1' ? '0' : '1';
    }

    public function render(): mixed
    {
        if (!$this->enabled) {
            return '';
        }
        $status = $this->runtime?->status() ?? [];
        $queueName = $this->events?->moduleQueueName('mqtt') ?? 'module.mqtt';
        $pending = 0;
        try {
            $pending = $this->queue?->stats($queueName)['pending'] ?? 0;
        } catch (\Throwable) {
            $pending = 0;
        }

        return $this->view('mqtt::yoyo.status', [
            'status' => $status,
            'pending' => $pending,
            'filters' => $this->settings?->queries() ?? [],
            'debug' => (bool)($this->settings?->bool('debug') ?? false),
            'open' => $this->open === '1',
        ]);
    }
}
