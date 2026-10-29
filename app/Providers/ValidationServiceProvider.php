<?php

declare(strict_types=1);

namespace LaIOT\Providers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator as TranslatorContract;
use Illuminate\Contracts\Validation\Factory as FactoryContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Factory;
use LaIOT\Database\DatabaseManager;
use LaIOT\Foundation\ServiceProvider;

/**
 * Штатный валидатор Illuminate с русскими сообщениями и общей БД приложения.
 *
 * Изменения:
 * 2026-10-28 — единая фабрика для HTTP, Yoyo и сервисов без полного Laravel.
 */
final class ValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(Translator::class, fn (): Translator => new Translator(
            new FileLoader(new Filesystem(), $this->app->root() . '/resources/lang'),
            'ru'
        ));
        $this->container->alias(Translator::class, TranslatorContract::class);
        $this->container->alias(Translator::class, 'translator');
        $this->container->singleton(Factory::class, static function (Container $container): Factory {
            $factory = new Factory($container->make(TranslatorContract::class), $container);
            // Проверки формата работают без БД; unique/exists используют тот же
            // менеджер и транзакции, что репозитории и сервисы приложения.
            $factory->setPresenceVerifier(new DatabasePresenceVerifier(
                $container->make(DatabaseManager::class)->resolver()
            ));

            return $factory;
        });
        $this->container->alias(Factory::class, FactoryContract::class);
        $this->container->alias(Factory::class, 'validator');
    }
}
