<?php

declare(strict_types=1);

namespace MajorDoMo\Foundation;

use Illuminate\Container\Container;

/**
 * Регистрация зависимостей и запуск одной подсистемы приложения.
 *
 * Сначала Application вызывает register() всех провайдеров, затем boot().
 * Конструктор не запускает сервисы: зависимости регистрируются лениво.
 *
 * Изменения:
 * 2026-10-26 — общий контракт провайдеров standalone-ядра MyDoMo.
 */
abstract class ServiceProvider
{
    public function __construct(
        protected readonly Application $app,
        protected readonly Container $container
    ) {
    }

    abstract public function register(): void;

    public function boot(): void
    {
    }
}
