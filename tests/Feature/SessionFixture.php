<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\Foundation\Application;
use LaIOT\Services\Auth\UserRepository;
use RuntimeException;

/**
 * Согласует тестовую сессию с реальным пользователем в изолированной SQLite.
 *
 * Изменения:
 * 2026-10-26 — HTTP-тесты проходят проверку пользователя и отпечатка пароля.
 */
final class SessionFixture
{
    public static function authenticate(Application $app): void
    {
        $session = $app->session();
        $user = $session->user();
        if ($user === null) {
            return;
        }
        $config = $app->database()->config();
        if (($config['driver'] ?? null) !== 'sqlite' || ($config['database'] ?? null) !== ':memory:') {
            throw new RuntimeException('Сессионная фикстура требует SQLite в памяти.');
        }

        $connection = $app->database()->connection();
        $migration = require $app->root() . '/database/migrations/2026_10_26_000008_create_users.php';
        $migration($connection);
        if (!$connection->table('users')->where('id', $user['id'])->exists()) {
            $connection->table('users')->insert([
                'id' => $user['id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'password' => password_hash('fixture-password', PASSWORD_DEFAULT),
                'is_admin' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $current = $app->container()->make(UserRepository::class)->sessionUser($user['id']);
        if ($current === null) {
            throw new RuntimeException('Пользователь тестовой сессии должен быть администратором.');
        }
        $session->login($current);
    }
}
