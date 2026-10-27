<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\Worker\ProcessLock;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет совместное использование блокировок несколькими экземплярами.
 *
 * 2026-10-27 — конфликт общей очереди и отдельной полосы не запускает worker.
 */
final class ProcessLockTest extends TestCase
{
    public function testSharedLanePreventsConcurrentWorkersAndReleasesPartialLocks(): void
    {
        $directory = sys_get_temp_dir() . '/mydomo-lock-test-' . bin2hex(random_bytes(8));
        $lane = new ProcessLock($directory);
        $all = new ProcessLock($directory);
        $other = new ProcessLock($directory);
        try {
            self::assertTrue($lane->acquire(['queue.events.1']));
            self::assertFalse($all->acquire(['queue.events.0', 'queue.events.1']));
            self::assertTrue($other->acquire(['queue.events.0']));
            $other->release();
            $lane->release();
            self::assertTrue($all->acquire(['queue.events.0', 'queue.events.1']));
            self::assertFalse($lane->acquire(['queue.events.1']));
        } finally {
            $lane->release();
            $all->release();
            $other->release();
            foreach (glob($directory . '/*.lock') ?: [] as $path) {
                unlink($path);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testLockIsHeldByAnotherProcessUntilItExits(): void
    {
        $directory = sys_get_temp_dir() . '/mydomo-lock-test-' . bin2hex(random_bytes(8));
        $code = <<<'PHP'
require $argv[1];
$lock = new MajorDoMo\Worker\ProcessLock($argv[2]);
echo $lock->acquire(['module.demo']) ? "locked\n" : "failed\n";
fgets(STDIN);
PHP;
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-r', $code, dirname(__DIR__, 2) . '/src/Worker/ProcessLock.php', $directory],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        $other = new ProcessLock($directory);
        try {
            self::assertSame("locked\n", fgets($pipes[1]));
            self::assertFalse($other->acquire(['module.demo']));
            fwrite($pipes[0], "exit\n");
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process));
            $process = null;
            self::assertTrue($other->acquire(['module.demo']));
        } finally {
            $other->release();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($process);
            }
            foreach (glob($directory . '/*.lock') ?: [] as $path) {
                unlink($path);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
}
