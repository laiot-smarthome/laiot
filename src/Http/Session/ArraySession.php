<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Session;

/**
 * Сессия в памяти для тестов и CLI-сценариев.
 */
final class ArraySession implements SessionStore
{
    /** @var array<string, mixed> */
    private array $data;

    private string $csrf;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->csrf = is_string($data['csrf'] ?? null) ? $data['csrf'] : bin2hex(random_bytes(32));
        unset($data['csrf']);
        $this->data = $data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function user(): ?array
    {
        $user = $this->data['user'] ?? null;

        return is_array($user) ? $user : null;
    }

    public function login(array $user): void
    {
        $this->csrf = bin2hex(random_bytes(32));
        $this->data['user'] = $user;
    }

    public function logout(): void
    {
        unset($this->data['user']);
        $this->csrf = bin2hex(random_bytes(32));
    }

    public function csrfToken(): string
    {
        return $this->csrf;
    }

    public function checkCsrf(mixed $token): bool
    {
        return is_string($token) && hash_equals($this->csrf, $token);
    }

    public function regenerate(): void
    {
        $this->csrf = bin2hex(random_bytes(32));
    }
}
