<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Session;

use RuntimeException;

/**
 * Native PHP-сессия с cookie-only настройками.
 *
 * Имя сессии отличается от старого `prj`, чтобы новый вход не смешивался
 * с legacy-сессией alpha.
 */
final class NativeSession implements SessionStore
{
    private const USER_KEY = 'mydomo_admin';
    private const CSRF_KEY = 'mydomo_csrf';
    private const STATE_KEY = 'mydomo_state';

    public function __construct(
        private readonly string $name = 'mydomo',
        private readonly string $cookiePath = '/'
    ) {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,31}$/D', $this->name)) {
            throw new RuntimeException('Недопустимое имя сессии.');
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (headers_sent()) {
                throw new RuntimeException('Сессия должна запускаться до вывода ответа.');
            }
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_cookies', '1');
            ini_set('session.use_trans_sid', '0');
            ini_set('session.gc_maxlifetime', '43200');
            session_name($this->name);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => $this->cookiePath,
                'domain' => '',
                'secure' => $this->isSecure(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            if (!session_start()) {
                throw new RuntimeException('Не удалось открыть сессию.');
            }
        }
        if (!is_array($_SESSION[self::STATE_KEY] ?? null)) {
            $_SESSION[self::STATE_KEY] = [];
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[self::STATE_KEY][$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[self::STATE_KEY][$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[self::STATE_KEY][$key]);
    }

    public function user(): ?array
    {
        $user = $_SESSION[self::USER_KEY] ?? null;

        return is_array($user) ? $user : null;
    }

    public function login(array $user): void
    {
        $this->regenerate();
        $_SESSION[self::USER_KEY] = $user;
    }

    public function logout(): void
    {
        unset($_SESSION[self::USER_KEY], $_SESSION[self::CSRF_KEY]);
        $_SESSION[self::STATE_KEY] = [];
        $this->regenerate();
    }

    public function csrfToken(): string
    {
        if (!is_string($_SESSION[self::CSRF_KEY] ?? null)) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CSRF_KEY];
    }

    public function checkCsrf(mixed $token): bool
    {
        return is_string($token) && hash_equals($this->csrfToken(), $token);
    }

    /**
     * Отпускает файловую блокировку сессии, не теряя уже прочитанные данные.
     *
     * После вызова $_SESSION остаётся доступным для чтения в этом процессе,
     * но новые записи уже не сохраняются — поэтому вызывать метод нужно
     * перед пользовательским кодом, а не в середине работы с сессией.
     */
    public function releaseLock(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public function regenerate(): void
    {
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Не удалось обновить сессию.');
        }
    }

    private function isSecure(): bool
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}
