<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\Services\Systemd\ProcessResult;
use LaIOT\Services\Systemd\ProcessRunner;
use LaIOT\Services\Systemd\SystemdController;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * systemd-контроллер агента: только проверенные module id и фиксированные
 * команды systemctl, без shell.
 */
final class SystemdControllerTest extends TestCase
{
    public function testStartStopRestartAndValidation(): void
    {
        $root = $this->root();
        try {
            $runner = new FakeProcessRunner();
            $controller = new SystemdController($runner, $root);
            self::assertTrue($controller->start('demo')->successful());
            self::assertTrue($controller->restart('demo')->successful());
            self::assertTrue($controller->stop('demo')->successful());
            self::assertSame([
                ['systemctl', 'start', 'laiot-worker@demo'],
                ['systemctl', 'restart', 'laiot-worker@demo'],
                ['systemctl', 'stop', 'laiot-worker@demo'],
            ], $runner->commands);

            foreach (['../demo', 'Demo', 'unknown'] as $invalid) {
                try {
                    $controller->start($invalid);
                    self::fail('Ожидалась ошибка для ' . $invalid);
                } catch (RuntimeException $error) {
                    self::assertNotEmpty($error->getMessage());
                }
            }
        } finally {
            $this->remove($root);
        }
    }

    public function testStatusParsing(): void
    {
        $root = $this->root();
        try {
            $runner = new FakeProcessRunner([
                'systemctl show laiot-worker@demo --no-pager --property=ActiveState,SubState,MainPID,ExecMainStartTimestamp' =>
                    new ProcessResult(0, "ActiveState=active\nSubState=running\nMainPID=4242\nExecMainStartTimestamp=Mon 2026-10-26 22:00:00 MSK\n", ''),
            ]);
            $status = (new SystemdController($runner, $root))->status('demo');
            self::assertSame('active', $status['active']);
            self::assertSame('running', $status['sub']);
            self::assertSame(4242, $status['pid']);
        } finally {
            $this->remove($root);
        }
    }

    public function testSyncStartsDesiredAndStopsStale(): void
    {
        $root = $this->root();
        try {
            $runner = new FakeProcessRunner([
                'systemctl list-units laiot-worker@* --all --no-legend --plain' =>
                    new ProcessResult(0, "laiot-worker@old.service loaded active running\n", ''),
            ]);
            $result = (new SystemdController($runner, $root))->sync(['demo']);
            self::assertSame(['demo'], $result['started']);
            self::assertSame(['old'], $result['stopped']);
            self::assertContains(['systemctl', 'stop', 'laiot-worker@old'], $runner->commands);
        } finally {
            $this->remove($root);
        }
    }

    public function testInstallUnitRendersPlaceholders(): void
    {
        $root = $this->root();
        $target = sys_get_temp_dir() . '/laiot-units-' . bin2hex(random_bytes(4));
        try {
            mkdir($root . '/deploy/systemd', 0775, true);
            file_put_contents(
                $root . '/deploy/systemd/laiot-worker@.service',
                "User=@@USER@@\nWorkingDirectory=@@ROOT@@/current\n"
            );
            $runner = new FakeProcessRunner();
            $file = (new SystemdController($runner, $root))->installUnit($target);
            self::assertSame('User=laiot', trim(explode("\n", (string)file_get_contents($file))[0]));
            self::assertStringContainsString($root . '/current', (string)file_get_contents($file));
            self::assertContains(['systemctl', 'daemon-reload'], $runner->commands);
        } finally {
            $this->remove($root);
            @unlink($target . '/laiot-worker@.service');
            @rmdir($target);
        }
    }

    public function testCoreUnitsAndAllowlist(): void
    {
        $root = $this->root();
        try {
            $runner = new FakeProcessRunner([
                'systemctl list-units laiot-core@* --all --no-legend --plain' =>
                    new ProcessResult(0, "laiot-core@timeouts.service loaded active running\n", ''),
            ]);
            $controller = new SystemdController($runner, $root);
            self::assertTrue($controller->coreStart('queue')->successful());
            self::assertContains(['systemctl', 'start', 'laiot-core@queue'], $runner->commands);

            $result = $controller->syncCore(['queue']);
            self::assertSame(['queue'], $result['started']);
            self::assertSame(['timeouts'], $result['stopped']);

            try {
                $controller->coreStart('events');
                self::fail('events ещё не разрешён');
            } catch (RuntimeException $error) {
                self::assertNotEmpty($error->getMessage());
            }
        } finally {
            $this->remove($root);
        }
    }

    private function root(): string
    {
        $root = sys_get_temp_dir() . '/laiot-systemd-' . bin2hex(random_bytes(4));
        mkdir($root . '/modules/core/demo', 0775, true);
        mkdir($root . '/modules/third', 0775, true);

        return $root;
    }

    private function remove(string $root): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($root);
    }
}

final class FakeProcessRunner implements ProcessRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    /**
     * @param array<string, ProcessResult> $responses
     */
    public function __construct(private array $responses = [])
    {
    }

    public function run(array $command, int $timeout = 20): ProcessResult
    {
        $this->commands[] = $command;
        $key = implode(' ', $command);

        return $this->responses[$key] ?? new ProcessResult(0, '', '');
    }
}
