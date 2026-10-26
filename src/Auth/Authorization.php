<?php

declare(strict_types=1);

namespace MajorDoMo\Auth;

use MajorDoMo\Http\Session\SessionStore;

/**
 * Проверка прав текущего пользователя.
 *
 * Пока роль хранится в сессии (`is_admin`); таблица ролей появится отдельным
 * этапом. Контракт `can()` уже позволяет модулям и middleware не знать,
 * откуда берётся роль.
 */
final readonly class Authorization
{
    public function __construct(private SessionStore $session)
    {
    }

    /**
     * @return array{id:int,username:string,name:string,is_admin?:bool,stamp?:string}|null
     */
    public function user(): ?array
    {
        return $this->session->user();
    }

    public function isAdmin(): bool
    {
        return (bool)($this->user()['is_admin'] ?? false);
    }

    public function can(string $permission): bool
    {
        if ($this->user() === null) {
            return false;
        }
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($permission, ['panel.view'], true);
    }
}
