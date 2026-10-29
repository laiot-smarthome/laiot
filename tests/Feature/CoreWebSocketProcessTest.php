<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\Console\Commands\CoreWorkerCommand;
use LaIOT\Console\ConsoleFactory;
use LaIOT\Database\DatabaseManager;
use LaIOT\Foundation\Application;
use LaIOT\Services\Worker\BackgroundTaskState;
use LaIOT\Services\Worker\CoreWorker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

/**
 * Общая core-команда и настоящий foreground/fork WebSocket на Linux.
 * 2026-10-29 — SQLite в отдельном файле, array-cache, только loopback.
 */
final class CoreWebSocketProcessTest extends TestCase
{
    /** @var list<string> */
    private array $roots = [];
    /** @var list<DatabaseManager> */
    private array $databases = [];

    public function testCommonCommandRejectsOnceAndUsesConfiguredCoreServer(): void
    {
        [$root, $config] = $this->fixture(8081);
        $config['websocket']['enabled'] = false;
        $app = new Application($root, $config);
        $app->boot();
        $this->databases[] = $app->database();
        $console = ConsoleFactory::make($app);
        self::assertFalse($console->has('websocket'));
        self::assertContains('websocket', CoreWorker::TASKS);
        $command = new CommandTester(new CoreWorkerCommand($app->container()->make(CoreWorker::class)));
        self::assertSame(1, $command->execute(['task' => 'websocket', '--once' => true]));
        self::assertStringContainsString('--once', $command->getDisplay());
        self::assertSame(1, $command->execute(['task' => 'websocket']));
        self::assertStringContainsString('WEBSOCKET_ENABLED=false', $command->getDisplay());
        self::assertSame('error', $app->container()->make(BackgroundTaskState::class)->get('core', 'websocket')['status']);
    }

    public function testCoreWebsocketStartsAndStopsAsOneLinuxService(): void
    {
        if (PHP_OS_FAMILY !== 'Linux' || !function_exists('pcntl_fork')) {
            self::markTestSkipped('Workerman fork и сигналы проверяются в Linux.');
        }
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertNotFalse($listener, $error);
        $port = (int)substr((string)stream_socket_get_name($listener, false), strrpos((string)stream_socket_get_name($listener, false), ':') + 1);
        fclose($listener);
        [$root, $config] = $this->fixture($port);
        $app = new Application($root, $config);
        $app->boot();
        $this->databases[] = $app->database();
        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_10_29_000002_create_websocket_tables.php';
        $migration($app->database()->connection());
        $app->database()->disconnect();
        $script = $root . '/run-core-websocket.php';
        file_put_contents($root . '/test-config.php', '<?php return ' . var_export($config, true) . ';');
        file_put_contents($script, '<?php require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';'
            . '$app=new \LaIOT\Foundation\Application(__DIR__,require __DIR__."/test-config.php");$app->boot();'
            . '$command=new \LaIOT\Console\Commands\CoreWorkerCommand($app->container()->make(\LaIOT\Services\Worker\CoreWorker::class));'
            . 'exit($command->run(new \Symfony\Component\Console\Input\ArrayInput(["task"=>"websocket"]),new \Symfony\Component\Console\Output\ConsoleOutput()));');
        $log = fopen($root . '/child.log', 'w');
        self::assertIsResource($log);
        $process = proc_open([PHP_BINARY, $script], [0 => ['file', '/dev/null', 'r'], 1 => $log, 2 => $log], $pipes);
        fclose($log);
        self::assertIsResource($process);
        $socket = false;
        try {
            for ($attempt = 0; $attempt < 50; $attempt++) {
                $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $error, 0.1);
                if ($socket !== false || !proc_get_status($process)['running']) {
                    break;
                }
                usleep(100000);
            }
            self::assertNotFalse($socket, (string)file_get_contents($root . '/child.log'));
            stream_set_timeout($socket, 3);
            fwrite($socket, "GET /websocket HTTP/1.1\r\nHost: localhost:$port\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Version: 13\r\nSec-WebSocket-Key: "
                . base64_encode(random_bytes(16)) . "\r\nSec-WebSocket-Protocol: laiot.v1\r\n\r\n");
            self::assertStringContainsString('101', (string)fgets($socket));
            self::assertFileExists($root . '/var/websocket/websocket.pid');
            $duplicate = new Process([PHP_BINARY, $script], timeout: 5);
            self::assertSame(1, $duplicate->run());
            self::assertStringContainsString('Core-worker уже запущен: websocket', $duplicate->getOutput());
            fclose($socket);
            $socket = false;
            proc_terminate($process, SIGTERM);
            for ($attempt = 0; $attempt < 50 && proc_get_status($process)['running']; $attempt++) {
                usleep(100000);
            }
            self::assertFalse(proc_get_status($process)['running'], 'Core WebSocket не завершился по SIGTERM.');
            clearstatcache();
            self::assertFileDoesNotExist($root . '/var/websocket/websocket.pid');
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
            if (proc_get_status($process)['running']) {
                proc_terminate($process, SIGKILL);
            }
            proc_close($process);
        }
    }

    /** @return array{string, array{database: array{driver: string, database: string, prefix: string}, cache: array{driver: string}, log: string, websocket: array{enabled: bool, host: string, port: int, workers: int}}} */
    private function fixture(int $port): array
    {
        $root = sys_get_temp_dir() . '/laiot-core-ws-' . bin2hex(random_bytes(6));
        mkdir($root . '/config', 0775, true);
        mkdir($root . '/var/log', 0775, true);
        mkdir($root . '/routes', 0775);
        file_put_contents($root . '/routes/web.php', '<?php return static function (): void {};');
        file_put_contents($root . '/routes/api.php', '<?php return static function (): void {};');
        touch($root . '/test.sqlite');
        foreach (glob(dirname(__DIR__, 2) . '/config/*.php') ?: [] as $file) {
            copy($file, $root . '/config/' . basename($file));
        }
        $this->roots[] = $root;
        return [$root, ['database' => ['driver' => 'sqlite', 'database' => $root . '/test.sqlite', 'prefix' => ''],
            'cache' => ['driver' => 'array'], 'log' => 'php://stderr',
            'websocket' => ['enabled' => true, 'host' => '127.0.0.1', 'port' => $port, 'workers' => 1]]];
    }

    protected function tearDown(): void
    {
        foreach ($this->databases as $database) {
            $database->disconnect();
        }
        foreach ($this->roots as $root) {
            $resolved = realpath($root);
            $prefix = rtrim((string)realpath(sys_get_temp_dir()), '/\\') . DIRECTORY_SEPARATOR . 'laiot-core-ws-';
            self::assertIsString($resolved);
            self::assertStringStartsWith($prefix, $resolved);
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($resolved, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($resolved);
        }
    }
}
