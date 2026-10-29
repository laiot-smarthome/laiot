<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Ранняя граница проверяется в дочернем PHP без Composer и рабочего .env.
 *
 * Изменения:
 * 2026-10-29 — исключения, parse/fatal, нехватка памяти и чистый HTTP-ответ.
 */
final class EarlyErrorHandlerTest extends TestCase
{
    public function testUncaughtExceptionBeforeComposerGetsDebugPage(): void
    {
        $process = $this->runFailure('exception', true);
        $html = $process->getOutput();

        self::assertStringContainsString('Трассировка', $html);
        self::assertStringContainsString('early failure', $html);
        self::assertStringContainsString('req-early', $html);
        self::assertStringContainsString('POST /broken', $html);
        self::assertStringNotContainsString('partial output', $html);
        self::assertStringNotContainsString('query-secret', $html);
        self::assertStringContainsString('STATUS=500', $process->getErrorOutput());
    }

    public function testExceptionBeforeConfigIsAlwaysGeneric(): void
    {
        $process = $this->runFailure('no-config', true);
        $html = $process->getOutput();

        self::assertStringContainsString('Внутренняя ошибка', $html);
        self::assertStringNotContainsString('early failure', $html);
        self::assertStringNotContainsString('Трассировка', $html);
    }

    public function testProductionHidesDetailsDespitePhpDisplayErrors(): void
    {
        $process = $this->runFailure('exception', false);
        $html = $process->getOutput();

        self::assertStringContainsString('Внутренняя ошибка', $html);
        self::assertStringNotContainsString('early failure', $html);
        self::assertStringNotContainsString('RuntimeException', $html);
        self::assertStringNotContainsString('partial output', $html);
        self::assertStringContainsString('early failure', $process->getErrorOutput());
    }

    public function testParseErrorInIncludedFileGetsOurPage(): void
    {
        $process = $this->runFailure('parse', true);

        self::assertStringContainsString('syntax error', $process->getOutput());
        self::assertStringContainsString('broken.php', $process->getOutput());
        self::assertStringContainsString('Трассировка', $process->getOutput());
        self::assertStringNotContainsString('partial output', $process->getOutput());
        self::assertStringContainsString('STATUS=500', $process->getErrorOutput());
    }

    public function testFatalShutdownGetsOurPageOnce(): void
    {
        $process = $this->runFailure('fatal', true);

        self::assertStringContainsString('fatal failure', $process->getOutput());
        self::assertSame(1, substr_count($process->getOutput(), '<!doctype html>'));
        self::assertStringNotContainsString('partial output', $process->getOutput());
        self::assertStringContainsString('STATUS=500', $process->getErrorOutput());
    }

    public function testMemoryExhaustionCanRenderGenericPage(): void
    {
        $process = $this->runFailure('memory', false);

        self::assertStringContainsString('Внутренняя ошибка', $process->getOutput());
        self::assertStringNotContainsString('Allowed memory size', $process->getOutput());
        self::assertStringContainsString('STATUS=500', $process->getErrorOutput());
    }

    public function testEarlyJsonKeepsErrorEnvelopeAndHidesDetails(): void
    {
        $process = $this->runFailure('exception', false, 'application/json');

        self::assertSame([
            'ok' => false,
            'error' => ['code' => 'server_error', 'message' => 'Internal Server Error'],
            'request_id' => 'req-early',
        ], json_decode($process->getOutput(), true));
    }

    public function testMemoryExhaustionCanRenderDebugPage(): void
    {
        $process = $this->runFailure('memory', true);

        self::assertStringContainsString('Allowed memory size', $process->getOutput());
        self::assertStringContainsString('Трассировка', $process->getOutput());
        self::assertSame(1, substr_count($process->getOutput(), '<!doctype html>'));
        self::assertStringContainsString('STATUS=500', $process->getErrorOutput());
    }

    public function testHeadDoesNotRenderBody(): void
    {
        $process = $this->runFailure('head', true);

        self::assertSame('', $process->getOutput());
        self::assertStringContainsString('STATUS=500', $process->getErrorOutput());
    }

    private function runFailure(string $scenario, bool $debug, string $accept = 'text/html'): Process
    {
        $directory = sys_get_temp_dir() . '/mydomo-early-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $fixture = $directory . '/broken.php';
        file_put_contents($fixture, '<?php function broken( {');
        $code = <<<'PHP'
            require $argv[1] . '/src/Http/EarlyErrorHandler.php';
            if ($argv[2] !== 'no-config') {
                require $argv[1] . '/src/Foundation/Config.php';
                // Каталог пустой: рабочий .env не читается.
                MajorDoMo\Foundation\Config::boot($argv[3]);
            }
            $_SERVER['REQUEST_URI'] = '/broken?token=query-secret';
            $_SERVER['REQUEST_METHOD'] = $argv[2] === 'head' ? 'HEAD' : 'POST';
            $_SERVER['HTTP_X_REQUEST_ID'] = 'req-early';
            $_SERVER['HTTP_ACCEPT'] = $argv[4];
            (new MajorDoMo\Http\EarlyErrorHandler($argv[1]))->register();
            register_shutdown_function(static function (): void {
                fwrite(STDERR, '\nSTATUS=' . http_response_code());
            });
            echo 'partial output';
            ob_start();
            echo 'nested partial output';
            if ($argv[2] === 'parse') {
                require $argv[3] . '/broken.php';
            }
            if ($argv[2] === 'fatal') {
                trigger_error('fatal failure', E_USER_ERROR);
            }
            if ($argv[2] === 'memory') {
                ini_set('memory_limit', '16M');
                $chunks = [];
                while (true) {
                    $chunks[] = str_repeat('x', 64 * 1024);
                }
            }
            throw new RuntimeException('early failure');
            PHP;
        $process = new Process([
            PHP_BINARY, '-d', 'display_errors=1', '-r', $code,
            dirname(__DIR__, 2), $scenario, $directory, $accept,
        ], env: ['APP_ENV' => 'testing', 'APP_DEBUG' => $debug ? '1' : '0']);
        $process->setTimeout(10);
        try {
            $process->run();

            return $process;
        } finally {
            unlink($fixture);
            rmdir($directory);
        }
    }
}
