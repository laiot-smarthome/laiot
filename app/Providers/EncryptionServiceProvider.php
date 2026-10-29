<?php

declare(strict_types=1);

namespace LaIOT\Providers;

use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Encryption\Encrypter;
use LaIOT\Foundation\ServiceProvider;
use LaIOT\Services\Security\EncryptionKey;

/**
 * Общий шифратор для сервисов и штатных encrypted-casts моделей.
 *
 * Изменения:
 * 2026-10-28 — ленивое разрешение APP_KEY и старые ключи для чтения данных.
 */
final class EncryptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(Encrypter::class, function (): Encrypter {
            $encrypter = new Encrypter(
                EncryptionKey::decode((string)$this->app->config('encryption.key', '')),
                EncryptionKey::CIPHER
            );

            return $encrypter->previousKeys(array_map(
                static fn (string $key): string => EncryptionKey::decode($key),
                (array)$this->app->config('encryption.previous_keys', [])
            ));
        });
        $this->container->alias(Encrypter::class, EncrypterContract::class);
        $this->container->alias(Encrypter::class, StringEncrypter::class);
        $this->container->alias(Encrypter::class, 'encrypter');
    }
}
