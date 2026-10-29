<?php

declare(strict_types=1);

namespace LaIOT\Tests\Integration;

use LaIOT\Services\Updates\UpdateDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Полное восстановление проверяется только в явно разрешённой одноразовой БД контейнера.
 * 2026-10-29 — настоящий дамп, замена снимка, удаление новой схемы и возврат данных/routines.
 */
final class UpdateDatabaseTest extends TestCase
{
    public function testAtomicSnapshotReplacementAndCompleteMysqlRestore(): void
    {
        if (getenv('LAIOT_TEST_ALLOW_DATABASE_RESTORE') !== 'disposable-update-container'
            || getenv('LAIOT_TEST_DB_HOST') !== 'update-db'
            || getenv('LAIOT_TEST_DB_NAME') !== 'laiot_update_disposable') {
            self::markTestSkipped('Нужен отдельный одноразовый контейнер БД с явным разрешением полного восстановления.');
        }
        $config = ['driver' => 'mysql', 'host' => 'update-db', 'port' => 3306, 'database' => 'laiot_update_disposable',
            'username' => 'root', 'password' => (string)getenv('LAIOT_TEST_DB_PASSWORD')];
        $pdo = new PDO('mysql:host=update-db;dbname=laiot_update_disposable;charset=utf8mb4', 'root', $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        self::assertSame('laiot_update_disposable', $pdo->query('SELECT DATABASE()')->fetchColumn());
        $directory = sys_get_temp_dir() . '/laiot-real-backup-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $backups = new UpdateDatabase($config, 'fixture-key-never-production', $directory);
        $pdo->exec('CREATE TABLE update_fixture (id INT PRIMARY KEY, message TEXT); INSERT INTO update_fixture VALUES (1, "first")');
        $pdo->exec('CREATE VIEW update_view AS SELECT message FROM update_fixture');
        $pdo->exec('CREATE PROCEDURE update_procedure() SELECT message FROM update_fixture');
        try {
            $backups->snapshot('beta-2.0.0', [], ['laiot-core@queue.service']);
            self::assertSame('beta-2.0.0', $backups->info('beta-2.0.0')['release']);
            $pdo->exec('UPDATE update_fixture SET message="last-before-switch"');
            $backups->snapshot('beta-2.0.0', [], ['laiot-core@queue.service']);
            self::assertCount(1, glob($directory . '/*.zip') ?: []);
            self::assertSame([], glob($directory . '/.prepare-*'));
            self::assertSame(['laiot-core@queue.service'], $backups->info('beta-2.0.0')['services']);
            $pdo->exec('UPDATE update_fixture SET message="new-version"');
            $pdo->exec('CREATE TABLE introduced_by_new_release (id INT)');
            $pdo->exec('CREATE PROCEDURE introduced_procedure() SELECT 2');
            $backups->restore('beta-2.0.0');
            self::assertSame('last-before-switch', $pdo->query('SELECT message FROM update_fixture')->fetchColumn());
            self::assertSame('last-before-switch', $pdo->query('SELECT message FROM update_view')->fetchColumn());
            self::assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='introduced_by_new_release'")->fetchColumn());
            self::assertSame(0, (int)$pdo->query("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='introduced_procedure'")->fetchColumn());
            self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='update_procedure'")->fetchColumn());
            $wrongKey = new UpdateDatabase($config, 'different-fixture-key', $directory);
            try {
                $wrongKey->restore('beta-2.0.0');
                self::fail('Снимок с другим APP_KEY принят.');
            } catch (\RuntimeException) {
                self::assertSame('last-before-switch', $pdo->query('SELECT message FROM update_fixture')->fetchColumn());
            }
        } finally {
            $pdo->exec('DROP VIEW IF EXISTS update_view; DROP TABLE IF EXISTS update_fixture, introduced_by_new_release; DROP PROCEDURE IF EXISTS update_procedure; DROP PROCEDURE IF EXISTS introduced_procedure');
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
