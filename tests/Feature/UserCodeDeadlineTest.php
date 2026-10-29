<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\Services\Runtime\UserCodeDeadline;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

/**
 * Только Linux/pcntl: реальные сигналы проверяются без БД и внешних сервисов.
 *
 * 2026-10-27 — общий срок вложенных вызовов и завершение при поглощённом таймауте.
 */
final class UserCodeDeadlineTest extends TestCase
{
    protected function setUp(): void
    {
        if (!(new UserCodeDeadline(1))->supported()) {
            self::markTestSkipped('Проверка сигналов требует pcntl в Linux.');
        }
    }

    public function testDeadlineUnwindsUserCodeAndCanBeUsedAgain(): void
    {
        $deadline = new UserCodeDeadline(1);
        try {
            $deadline->run(static fn (): int => sleep(3));
            self::fail('Обработчик должен завершиться по сроку.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('превысил', $error->getMessage());
        }
        self::assertSame(42, $deadline->run(static fn (): int => 42));
    }

    public function testNestedInvocationCannotExtendOuterDeadline(): void
    {
        $deadline = new UserCodeDeadline(1);
        $this->expectException(RuntimeException::class);
        $deadline->run(static function () use ($deadline): void {
            usleep(700000);
            $deadline->run(static fn () => usleep(700000));
        });
    }

    public function testSwallowingTimeoutCannotKeepWorkerAlive(): void
    {
        $autoload = var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true);
        $code = 'require ' . $autoload . '; $guard = new \\LaIOT\\Services\\Runtime\\UserCodeDeadline(1);'
            . '$guard->run(static function (): void {'
            . 'try { while (true) { usleep(10000); } }'
            . 'catch (\\Throwable) { while (true) { usleep(10000); } } });';
        $process = new Process([PHP_BINARY, '-r', $code]);
        $process->setTimeout(5);
        try {
            $process->run();
        } catch (ProcessSignaledException $error) {
            self::assertSame(SIGALRM, $error->getSignal());
        }
        self::assertTrue($process->hasBeenSignaled());
        self::assertSame(SIGALRM, $process->getTermSignal());
    }

    public function testSwallowingTimeoutAndReturningStillFails(): void
    {
        $autoload = var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true);
        $code = 'require ' . $autoload . '; $guard = new \\LaIOT\\Services\\Runtime\\UserCodeDeadline(1);'
            . 'try { $guard->run(static function (): void {'
            . 'try { sleep(3); } catch (\\RuntimeException) {} }); exit(0); }'
            . 'catch (\\RuntimeException $error) { echo $error->getMessage(); exit(1); }';
        $process = new Process([PHP_BINARY, '-r', $code]);
        $process->setTimeout(5);
        $process->run();
        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('превысил', $process->getOutput());
    }
}
