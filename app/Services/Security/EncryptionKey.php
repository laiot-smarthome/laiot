<?php

declare(strict_types=1);

namespace LaIOT\Services\Security;

use Illuminate\Encryption\Encrypter;
use Illuminate\Encryption\MissingAppKeyException;
use RuntimeException;
use SensitiveParameter;

/**
 * Единый формат ключа установщика и шифратора; ошибки не содержат секрет.
 *
 * Изменения:
 * 2026-10-28 — APP_KEY в формате Laravel для AES-256-CBC.
 */
final class EncryptionKey
{
    public const CIPHER = 'AES-256-CBC';

    public static function generate(): string
    {
        return 'base64:' . base64_encode(Encrypter::generateKey(self::CIPHER));
    }

    public static function decode(#[SensitiveParameter] string $value): string
    {
        if ($value === '') {
            throw new MissingAppKeyException('Не настроен APP_KEY. Ключ создаётся установщиком приложения.');
        }
        $key = str_starts_with($value, 'base64:') ? base64_decode(substr($value, 7), true) : $value;
        if ($key === false || !Encrypter::supported($key, self::CIPHER)) {
            throw new RuntimeException('APP_KEY должен содержать 32 байта или их представление base64:.');
        }

        return $key;
    }
}
