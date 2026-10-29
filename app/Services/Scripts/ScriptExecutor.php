<?php

declare(strict_types=1);

namespace LaIOT\Services\Scripts;

use LaIOT\Foundation\Application;
use LaIOT\Services\Runtime\CodeRunner;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Выполнение кода сценария в выбранном окружении.
 *
 * С ядром код идёт через `CodeRunner` в текущем процессе: доступны обёртки
 * `gg/sg/callMethod`, `$params` и сервисы, загруженные ядром. Без ядра код
 * запускается отдельным процессом `php` с временным файлом: ядро и его
 * глобальные функции не загружаются, поэтому это «обычный PHP-файл», а не
 * sandbox. В обоих случаях `$params` передаётся сценарию.
 *
 * Без ядра дочерний процесс возвращает JSON с выводом и результатом;
 * `include` без `return` даёт `1`, как и в обычном PHP.
 *
 * 2026-10-27 — сценарий может выполняться без обёрток ядра.
 */
final readonly class ScriptExecutor
{
    /** Сколько ждать отдельный процесс: зависший сценарий не должен держать очередь. */
    public const TIMEOUT_SECONDS = 10;

    private const STANDALONE_RUNNER = <<<'PHP'
<?php

declare(strict_types=1);

$payload = json_decode((string)file_get_contents($argv[1]), true);
$params = is_array($payload['params'] ?? null) ? $payload['params'] : [];
$display = static function (mixed $value): string {
    if ($value === null || is_scalar($value)) {
        return var_export($value, true);
    }
    if (is_array($value)) {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json === false ? 'array' : $json;
    }

    return get_debug_type($value);
};

$report = ['ok' => false, 'output' => '', 'result' => '', 'error' => ''];
ob_start();
try {
    $value = include $argv[2];
    $report['ok'] = true;
    $report['result'] = $display($value);
} catch (Throwable $error) {
    $report['error'] = $error::class . ': ' . $error->getMessage();
}
$report['output'] = (string)ob_get_clean();
file_put_contents($argv[3], json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
PHP;

    public function __construct(
        private CodeRunner $runner,
        private Application $app
    ) {
    }

    /**
     * Выполняет код и возвращает значение, не перехватывая вывод.
     *
     * @param array<string, mixed> $params
     */
    public function runValue(string $code, array $params, bool $useCore): mixed
    {
        if ($useCore) {
            return $this->runner->run($code, $params);
        }

        $result = $this->standalone($code, $params);
        if ($result['output'] !== '') {
            echo $result['output'];
        }
        if (!$result['ok']) {
            throw new RuntimeException($result['error']);
        }

        return $result['result'];
    }

    /**
     * Выполняет код для кнопки «Выполнить»: вывод и результат перехвачены.
     *
     * @param array<string, mixed> $params
     * @return array{ok:bool,output:string,result:string,error:string,duration_ms:int}
     */
    public function execute(string $code, array $params, bool $useCore): array
    {
        if (!$useCore) {
            return $this->standalone($code, $params);
        }

        $started = hrtime(true);
        ob_start();
        try {
            $value = $this->runner->run($code, $params);
            $output = (string)ob_get_clean();

            return [
                'ok' => true,
                'output' => $output,
                'result' => $this->display($value),
                'error' => '',
                'duration_ms' => $this->elapsed($started),
            ];
        } catch (Throwable $error) {
            $output = (string)ob_get_clean();

            return [
                'ok' => false,
                'output' => $output,
                'result' => '',
                'error' => $error::class . ': ' . $error->getMessage(),
                'duration_ms' => $this->elapsed($started),
            ];
        }
    }

    /**
     * Запускает код отдельным процессом без bootstrap ядра.
     *
     * @param array<string, mixed> $params
     * @return array{ok:bool,output:string,result:string,error:string,duration_ms:int}
     */
    private function standalone(string $code, array $params): array
    {
        $started = hrtime(true);
        $scriptFile = tempnam(sys_get_temp_dir(), 'laiot_plain_');
        $runnerFile = tempnam(sys_get_temp_dir(), 'laiot_plain_runner_');
        $payloadFile = tempnam(sys_get_temp_dir(), 'laiot_plain_params_');
        $resultFile = tempnam(sys_get_temp_dir(), 'laiot_plain_result_');
        if ($scriptFile === false || $runnerFile === false || $payloadFile === false || $resultFile === false) {
            return $this->failure('Не удалось создать временные файлы.', $started);
        }

        try {
            file_put_contents($scriptFile, "<?php\n" . $code);
            file_put_contents($runnerFile, self::STANDALONE_RUNNER);
            file_put_contents(
                $payloadFile,
                json_encode(['params' => $params], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            );

            $process = new Process(
                [$this->phpBinary(), $runnerFile, $payloadFile, $scriptFile, $resultFile],
                $this->app->root()
            );
            $process->setTimeout(self::TIMEOUT_SECONDS);
            $process->run();

            $report = $this->decodeReport((string)@file_get_contents($resultFile));
            if ($report !== null) {
                $report['duration_ms'] = $this->elapsed($started);

                return $report;
            }

            // Сценарий завершил процесс сам (exit) или упал до записи отчёта.
            $output = (string)$process->getOutput();
            if ($process->isSuccessful()) {
                return [
                    'ok' => true,
                    'output' => $output,
                    'result' => '',
                    'error' => '',
                    'duration_ms' => $this->elapsed($started),
                ];
            }

            $error = trim((string)$process->getErrorOutput());

            return $this->failure(
                $error !== '' ? $error : 'Процесс завершился с кодом ' . (int)$process->getExitCode() . '.',
                $started
            );
        } catch (ProcessTimedOutException) {
            return $this->failure('Превышено время выполнения (' . self::TIMEOUT_SECONDS . ' с).', $started);
        } catch (Throwable $error) {
            return $this->failure($error::class . ': ' . $error->getMessage(), $started);
        } finally {
            @unlink($scriptFile);
            @unlink($runnerFile);
            @unlink($payloadFile);
            @unlink($resultFile);
        }
    }

    /**
     * @return array{ok:bool,output:string,result:string,error:string}|null
     */
    private function decodeReport(string $json): ?array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['ok'])) {
            return null;
        }

        return [
            'ok' => (bool)$decoded['ok'],
            'output' => (string)($decoded['output'] ?? ''),
            'result' => (string)($decoded['result'] ?? ''),
            'error' => (string)($decoded['error'] ?? ''),
        ];
    }

    /**
     * @return array{ok:bool,output:string,result:string,error:string,duration_ms:int}
     */
    private function failure(string $message, int $started): array
    {
        return [
            'ok' => false,
            'output' => '',
            'result' => '',
            'error' => $message,
            'duration_ms' => $this->elapsed($started),
        ];
    }

    private function display(mixed $value): string
    {
        if ($value === null || is_scalar($value)) {
            return var_export($value, true);
        }
        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR);

            return $json === false ? 'array' : $json;
        }

        return get_debug_type($value);
    }

    private function elapsed(int $started): int
    {
        return (int)round((hrtime(true) - $started) / 1_000_000);
    }

    /**
     * В FPM `PHP_BINARY` — это php-fpm, поэтому берём CLI рядом с ним.
     */
    private function phpBinary(): string
    {
        $candidate = PHP_BINDIR . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php');
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }

        return PHP_BINARY;
    }
}
