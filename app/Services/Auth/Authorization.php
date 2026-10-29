<?php

declare(strict_types=1);

namespace LaIOT\Services\Auth;

use LaIOT\Http\Session\SessionStore;
use Throwable;

/**
 * Проверка прав текущего пользователя.
 *
 * Основной источник — таблицы ролей; если БД/миграции ещё недоступны,
 * используется сессионный `is_admin` как безопасный fallback.
 *
 * 2026-10-27 — флаг администратора всегда даёт заявленный полный доступ,
 * независимо от набора дополнительно назначенных ролей.
 */
final readonly class Authorization
{
    public function __construct(
        private SessionStore $session,
        private ?RoleRepository $roles = null
    ) {
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
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        if ($this->roles instanceof RoleRepository) {
            try {
                if (in_array('admin', $this->roles->rolesForUser($user['id']), true)) {
                    return true;
                }
            } catch (Throwable) {
                // Ниже — fallback на сессию.
            }
        }

        return (bool)($user['is_admin'] ?? false);
    }

    public function can(string $permission): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        if ((bool)($user['is_admin'] ?? false)) {
            return true;
        }

        if ($this->roles instanceof RoleRepository) {
            try {
                $granted = $this->roles->permissionsForUser($user['id']);
                if (in_array('*', $granted, true) || in_array($permission, $granted, true)) {
                    return true;
                }
                if ($granted !== []) {
                    return false;
                }
            } catch (Throwable) {
                // Fallback ниже.
            }
        }

        return $this->isAdmin() || in_array($permission, ['panel.view'], true);
    }
}
