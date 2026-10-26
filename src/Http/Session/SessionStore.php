<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Session;

/**
 * Минимальный контракт сессии для нового ядра.
 *
 * Позволяет тестам использовать ArraySession без native session и не
 * привязывает контроллеры к суперглобалу $_SESSION.
 */
interface SessionStore
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function forget(string $key): void;

    /**
     * @return array{id:int,username:string,name:string}|null
     */
    public function user(): ?array;

    /**
     * @param array{id:int,username:string,name:string} $user
     */
    public function login(array $user): void;

    public function logout(): void;

    public function csrfToken(): string;

    public function checkCsrf(mixed $token): bool;

    public function regenerate(): void;
}
