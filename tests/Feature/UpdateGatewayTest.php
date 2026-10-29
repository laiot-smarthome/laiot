<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\Services\Updates\UpdateFiles;
use LaIOT\Services\Updates\UpdateState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Аварийный экран запускается отдельным PHP без Composer и без БД.
 * 2026-10-29 — ключ доступа, утечки сведений, CSRF и прерванная операция.
 */
final class UpdateGatewayTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/laiot-gateway-' . bin2hex(random_bytes(6));
        UpdateFiles::json($this->root . '/var/updates/operation.json', [
            'id' => 'fixture-operation', 'access' => hash('sha256', str_repeat('a', 64)),
            'status' => 'running', 'heartbeat' => time() - 120, 'phase' => 'database',
            'source' => ['id' => 'beta-2.0.0'], 'target' => ['id' => 'beta-2.1.0'],
            'message' => 'fixture private message', 'events' => [],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/var/updates/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->root . '/var/updates');
        rmdir($this->root . '/var');
        rmdir($this->root);
    }

    public function testRecoveryWithoutComposerShowsChoiceOnlyToValidKey(): void
    {
        $private = $this->render(['laiot_update' => str_repeat('a', 64)]);
        self::assertStringContainsString('Обновление было прервано', $private);
        self::assertStringContainsString('Попробовать ещё раз', $private);
        self::assertStringContainsString('Вернуть прежнюю версию', $private);
        self::assertStringContainsString('Этап 5 из 8', $private);
        $public = $this->render([]);
        self::assertStringContainsString('recovery.key', $public);
        self::assertStringNotContainsString('fixture private message', $public);
        self::assertStringNotContainsString('beta-2.1.0', $public);
    }

    public function testInvalidCsrfDoesNotQueueRecovery(): void
    {
        $html = $this->render(['laiot_update' => str_repeat('a', 64)], [
            'action' => 'rollback', 'operation' => 'fixture-operation', 'csrf' => 'wrong', 'confirmed' => '1',
        ]);
        self::assertStringContainsString('запрос устарел', $html);
        $operation = (new UpdateState($this->root . '/var/updates'))->raw();
        self::assertSame('running', $operation['status']);
        self::assertArrayNotHasKey('action', $operation);
    }

    public function testMaintenanceBlocksApplicationBeforeLoadingItsCode(): void
    {
        UpdateFiles::write($this->root . '/var/updates/maintenance.flag', 'fixture');
        $html = $this->render([], [], '/api/v1/example');
        self::assertStringContainsString('Запись временно остановлена', $html);
        self::assertStringNotContainsString('application was loaded', $html);
    }

    public function testCommittedOperationStillClosedByGateRequiresExplicitCompletion(): void
    {
        $state = new UpdateState($this->root . '/var/updates');
        $state->save(['status' => 'succeeded', 'completion' => ['status' => 'succeeded']]);
        UpdateFiles::write($state->directory . '/maintenance.flag', 'fixture');
        self::assertSame('interrupted', $state->read()['status']);
    }

    public function testProgressRequiresAccessAndExcludesRecoveryCredentials(): void
    {
        $path = '/admin/updates/recovery?status=1';
        $public = json_decode($this->render([], [], $path), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['error' => 'access_required'], $public);
        $private = json_decode($this->render(['laiot_update' => str_repeat('a', 64)], [], $path), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('interrupted', $private['status']);
        self::assertSame(5, $private['step']);
        self::assertSame('fixture private message', $private['message']);
        self::assertArrayNotHasKey('access', $private);
        self::assertArrayNotHasKey('source', $private);
        self::assertStringNotContainsString(hash('sha256', str_repeat('a', 64)), json_encode($private, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, string> $cookies
     * @param array<string, string> $post
     */
    private function render(array $cookies, array $post = [], string $path = '/admin/updates/recovery'): string
    {
        $source = dirname(__DIR__, 2) . '/app/Services/Updates/UpdateGateway.php';
        $code = '$_SERVER=' . var_export(['REQUEST_URI' => $path, 'REQUEST_METHOD' => $post === [] ? 'GET' : 'POST'], true)
            . ';parse_str((string)parse_url($_SERVER["REQUEST_URI"], PHP_URL_QUERY), $_GET);$_COOKIE=' . var_export($cookies, true) . ';$_POST=' . var_export($post, true)
            . ';require ' . var_export($source, true) . ';\\LaIOT\\Services\\Updates\\UpdateGateway::enter(' . var_export($this->root, true) . ');echo "application was loaded";';
        $process = new Process([PHP_BINARY, '-r', $code], null, [], null, 10);
        $process->mustRun();
        return $process->getOutput();
    }
}
