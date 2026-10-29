<?php

declare(strict_types=1);

namespace LaIOT\Services\Auth;

/**
 * Проверка логина/пароля администратора через UserRepository.
 */
final readonly class AdminAuthenticator
{
    public function __construct(private UserRepository $users)
    {
    }

    /**
     * @return array{id:int,username:string,name:string,stamp:string,is_admin:bool}|null
     */
    public function attempt(string $username, string $password): ?array
    {
        return $this->users->verifyAdmin($username, $password);
    }
}
