<?php

declare(strict_types=1);

namespace MajorDoMo\Settings;

use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\MySqlConnection;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Проверяет новое подключение без переключения действующего Eloquent resolver.
 *
 * 2026-10-27 — смена БД требует готовой схемы и того же администратора.
 */
class DatabaseSettingsGuard
{
    /**
     * @param array<string, string> $values
     * @param array<string, mixed> $user
     */
    public function verify(array $values, array $user): void
    {
        $config = [
            'driver' => 'mysql', 'host' => $values['DB_HOST'], 'port' => (int)$values['DB_PORT'],
            'database' => $values['DB_NAME'], 'username' => $values['DB_USER'], 'password' => $values['DB_PASSWORD'],
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
            'options' => [PDO::ATTR_TIMEOUT => 5],
        ];
        $connection = null;
        try {
            $pdo = (new MySqlConnector())->connect($config);
            $connection = new MySqlConnection($pdo, $values['DB_NAME'], '', $config);
            foreach (['users', 'classes', 'objects', 'properties', 'queue_jobs', 'module_registry'] as $table) {
                if (!$connection->getSchemaBuilder()->hasTable($table)) {
                    throw new RuntimeException('Неполная схема.');
                }
            }
            $admin = $connection->table('users')->where('id', (int)($user['id'] ?? 0))
                ->where('username', (string)($user['username'] ?? ''))
                ->where('is_admin', 1)->whereNull('deleted_at')->first();
            if ($admin === null || !hash_equals((string)($user['stamp'] ?? ''), hash('sha256', (string)$admin->password))) {
                throw new RuntimeException('Администратор не совпадает.');
            }
        } catch (Throwable) {
            // Сообщения драйвера могут содержать адреса и учётные данные.
            throw new RuntimeException('Новое подключение не прошло проверку. Нужна доступная база MyDoMo с готовой схемой и текущим администратором с тем же паролем. Файл .env не изменён.');
        } finally {
            $connection?->disconnect();
        }
    }
}
