<?php

declare(strict_types=1);

namespace LaIOT\Providers;

use LaIOT\Foundation\ServiceProvider;
use LaIOT\Services\Events\SystemEvents;
use LaIOT\Services\Xray\ValueSnapshot;
use LaIOT\Services\Xray\XrayJournal;
use LaIOT\Services\Xray\XrayService;
use LaIOT\Support\AppContext;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Диагностика ядра, без SDK-модуля и зависимости от запущенного WebSocket.
 * 2026-10-30 — общий журнал, системные события и перенаправление dev dump().
 */
final class XrayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(ValueSnapshot::class);
        $this->container->singleton(XrayJournal::class);
        $this->container->singleton(XrayService::class);
    }

    public function boot(): void
    {
        $this->container->make(SystemEvents::class)->observe(function (string $event, array $data): void {
            $this->container->make(XrayJournal::class)->append('event', $event, 'events', [
                'value' => $this->container->make(ValueSnapshot::class)->capture($data),
            ]);
        });
        // Symfony загружает глобальный dump() раньше helpers только в dev.
        // Его handler использует текущий AppContext, а не приложение первого boot.
        if (class_exists(VarDumper::class)) {
            VarDumper::setHandler(static function (mixed $value, ?string $label = null): void {
                AppContext::get()?->container()->make(XrayJournal::class)->dump($value, $label ?? '');
            });
        }
    }
}
