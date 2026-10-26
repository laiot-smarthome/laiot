<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Integration;

use Illuminate\Database\Schema\Blueprint;
use MajorDoMo\Database\DatabaseManager;
use PHPUnit\Framework\TestCase;

/**
 * Проверка настоящего MariaDB/MySQL, а не SQLite.
 *
 * Запускается только при явно заданном одноразовом стенде:
 * MYDOMO_TEST_DB_HOST, MYDOMO_TEST_DB_PORT, MYDOMO_TEST_DB_NAME,
 * MYDOMO_TEST_DB_USER, MYDOMO_TEST_DB_PASSWORD.
 */
final class MariaDbConnectionTest extends TestCase
{
    public function testConnectionSchemaAndQuery(): void
    {
        $host = getenv('MYDOMO_TEST_DB_HOST');
        if ($host === false || $host === '') {
            self::markTestSkipped('MariaDB test environment is not configured.');
        }

        $database = new DatabaseManager([
            'driver' => 'mysql',
            'host' => $host,
            'port' => (int)(getenv('MYDOMO_TEST_DB_PORT') ?: 3306),
            'database' => (string)(getenv('MYDOMO_TEST_DB_NAME') ?: 'mydomo_test'),
            'username' => (string)(getenv('MYDOMO_TEST_DB_USER') ?: 'root'),
            'password' => (string)(getenv('MYDOMO_TEST_DB_PASSWORD') ?: ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]);

        self::assertTrue($database->ping());

        $table = 'mydomo_stage1_' . bin2hex(random_bytes(4));
        $schema = $database->schema();
        $schema->create($table, static function (Blueprint $blueprint): void {
            $blueprint->increments('ID');
            $blueprint->string('MESSAGE', 100);
        });

        try {
            $database->connection()->table($table)->insert(['MESSAGE' => 'ok']);
            $value = $database->connection()->table($table)->where('ID', 1)->value('MESSAGE');

            self::assertSame('ok', $value);
        } finally {
            $schema->dropIfExists($table);
        }
    }
}
