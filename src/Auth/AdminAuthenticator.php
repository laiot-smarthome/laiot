<?php

declare(strict_types=1);

namespace MajorDoMo\Auth;

use Closure;
use MajorDoMo\Core\Users\UserAccounts;

/**
 * Проверка логина/пароля администратора через существующий сервис users.
 *
 * Сервис users принимает PDO, поэтому новый слой не открывает второе
 * mysqli-соединение и использует те же хеши паролей. Фабрика вызывается
 * только при попытке входа: страница /login не должна трогать БД.
 */
final readonly class AdminAuthenticator
{
    /**
     * @param Closure(): UserAccounts $users
     */
    public function __construct(private Closure $users)
    {
    }

    /**
     * @return array{id:int,username:string,name:string,stamp:string}|null
     */
    public function attempt(string $username, string $password): ?array
    {
        $user = ($this->users)()->verifyAdmin($username, $password);

        if ($user === null) {
            return null;
        }

        return [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'name' => (string)$user['name'],
            'stamp' => (string)$user['stamp'],
        ];
    }
}
