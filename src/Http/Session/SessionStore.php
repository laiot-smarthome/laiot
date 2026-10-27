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
     * @return array{id:int,username:string,name:string,is_admin?:bool,stamp?:string}|null
     */
    public function user(): ?array;

    /**
     * @param array{id:int,username:string,name:string,is_admin?:bool,stamp?:string} $user
     */
    public function login(array $user): void;

    public function logout(): void;

    public function csrfToken(): string;

    public function checkCsrf(mixed $token): bool;

    /**
     * Освобождает блокировку сессии до конца запроса.
     *
     * Native-сессия держит файл заблокированным: пока идёт долгий запуск
     * сценария, другие вкладки той же сессии ждут. Метод вызывается перед
     * синхронным пользовательским кодом, когда запись в сессию уже не нужна.
     */
    public function releaseLock(): void;

    public function regenerate(): void;
}
