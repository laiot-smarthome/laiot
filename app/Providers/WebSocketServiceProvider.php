<?php

declare(strict_types=1);

namespace LaIOT\Providers;

use Illuminate\Container\Container;
use LaIOT\Database\DatabaseManager;
use LaIOT\Foundation\ServiceProvider;
use LaIOT\Services\Events\SystemEvents;
use LaIOT\Services\Logging\LoggerFactory;
use LaIOT\Services\WebSocket\WebSocketConfig;
use LaIOT\Services\WebSocket\WebSocketEventRepository;
use LaIOT\Services\WebSocket\WebSocketPublisher;
use LaIOT\Services\WebSocket\WebSocketRuntime;
use LaIOT\Services\WebSocket\WebSocketTicketRepository;
use LaIOT\Services\WebSocket\WebSocketTicketService;

/**
 * Подсистема WebSocket.
 *
 * Регистрация ленивая: HTTP-запрос без websocket-якорей не открывает БД и не
 * создаёт сервер. Публикация подключается к PropertyValueService опционально,
 * поэтому старые тестовые сборки сервиса остаются рабочими.
 *
 * 2026-10-29 — провайдер WebSocket.
 */
final class WebSocketServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(WebSocketConfig::class, fn (): WebSocketConfig => WebSocketConfig::fromArray(
            (array)$this->app->config('websocket', [])
        ));
        $this->container->singleton(WebSocketEventRepository::class);
        $this->container->singleton(WebSocketTicketRepository::class);
        $this->container->singleton(WebSocketTicketService::class);
        $this->container->singleton(WebSocketPublisher::class, fn (Container $container): WebSocketPublisher => new WebSocketPublisher(
            $container->make(DatabaseManager::class),
            $container->make(WebSocketConfig::class),
            $container->make(LoggerFactory::class)->channel('websocket')
        ));
        $this->container->bind(WebSocketRuntime::class, fn (Container $container): WebSocketRuntime => new WebSocketRuntime(
            $container->make(WebSocketConfig::class),
            $container->make(WebSocketTicketService::class),
            $container->make(WebSocketTicketRepository::class),
            $container->make(WebSocketEventRepository::class),
            $container->make(DatabaseManager::class),
            $container->make(LoggerFactory::class)->channel('websocket')
        ));
    }

    public function boot(): void
    {
        // Все system.* и module.<id>.* проходят через SystemEvents. Наблюдатель
        // кладёт их в тот же outbox, чтобы открытая панель получала системные
        // события наравне с изменениями свойств.
        $this->container->make(SystemEvents::class)->observe(function (string $event, array $data): void {
            $this->container->make(WebSocketPublisher::class)->publishSystem($event, $data);
        });
    }
}
