<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use LaIOT\Deployment\FastPanel;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/deploy/lib/FastPanel.php';

/**
 * Контракт установленной FastPanel: JSON-типы, публичный каталог и откат.
 * 2026-10-29 — изолированные проверки без HTTPS, панели и системных служб.
 */
final class FastPanelProvisionTest extends TestCase
{
    private const BASE = '/var/www/demo/data/www/demo.test';

    public function testProfileLeavesTwoWorkersForSmallVpsAndCapsLargeHosts(): void
    {
        self::assertSame(2, FastPanel::children(894, 1));
        self::assertSame(5, FastPanel::children(2048, 2));
        self::assertSame(4, FastPanel::children(8192, 1));
        self::assertSame(32, FastPanel::children(65536, 64));
    }

    public function testConfigurationProtectsPrivateFilesAndAllowsLongRequests(): void
    {
        $frontend = FastPanel::nginx($this->configuration(self::BASE)['frontend']);
        self::assertStringContainsString('location = /index.php {', $frontend);
        self::assertStringContainsString('location ~* \.php(?:/|$) { return 404; }', $frontend);
        self::assertStringContainsString('(?!well-known', $frontend);
        self::assertStringContainsString('fastcgi_read_timeout 660s;', $frontend);
        self::assertStringContainsString('proxy_pass http://127.0.0.1:8081;', $frontend);
        self::assertSame($frontend, FastPanel::nginx($frontend));
        $pool = FastPanel::fpm($this->configuration(self::BASE)['backend'], 2);
        self::assertStringContainsString('request_terminate_timeout = 660s', $pool);
        self::assertStringContainsString('php_admin_value[max_execution_time] = 600', $pool);
        self::assertStringContainsString('pm.max_children = 2', $pool);
        self::assertSame($pool, FastPanel::fpm($pool, 2));
    }

    public function testUnknownNginxIsRejectedInsteadOfOverwritten(): void
    {
        $this->expectException(RuntimeException::class);
        FastPanel::nginx('server { root /srv/other; }');
    }

    public function testPanelContractUsesStringPhpVersionAndWorkDir(): void
    {
        $this->exercisePanel(false);
    }

    public function testFailedConfigurationRestoresOriginalBackendAndNginx(): void
    {
        $this->exercisePanel(true);
    }

    private function exercisePanel(bool $reject): void
    {
        $state = sys_get_temp_dir() . '/laiot-fastpanel-' . bin2hex(random_bytes(8));
        mkdir($state, 0700);
        $root = self::BASE;
        $backends = [];
        $configurations = [];
        $transport = function (string $method, string $path, ?array $body) use (&$root, &$backends, &$configurations, &$reject): array {
            if ($method === 'GET' && $path === '/sites/7') {
                return ['data' => ['index_dir' => self::BASE, 'main_backend' => ['id' => 8,
                    'handler' => 'php_fpm', 'handler_version' => '83', 'work_dir' => '', 'workers_count' => 2]]];
            }
            if ($method === 'GET' && $path === '/sites/7/configuration') {
                return ['data' => $this->configuration($root)];
            }
            if ($method === 'PUT' && $path === '/sites/backend/8') {
                $backends[] = $body;
                $root = self::BASE . ($body['work_dir'] === '' ? '' : '/' . $body['work_dir']);
                return ['data' => $body];
            }
            if ($method === 'PUT' && $path === '/sites/7/configuration') {
                if ($reject) {
                    $reject = false;
                    throw new RuntimeException('Configuration rejected', 503);
                }
                $configurations[] = $body;
                return ['data' => $body];
            }
            throw new RuntimeException('Unexpected API call: ' . $method . ' ' . $path);
        };
        $expectedFailure = $reject;
        try {
            try {
                (new FastPanel($state, $transport))->configure(7, self::BASE . '/current/public', '8.3', 894, 1);
                self::assertFalse($expectedFailure);
            } catch (RuntimeException $error) {
                self::assertTrue($expectedFailure);
                self::assertStringContainsString('прежняя конфигурация возвращена', $error->getMessage());
            }
            self::assertSame('83', $backends[0]['handler_version']);
            self::assertSame('current/public', $backends[0]['work_dir']);
            self::assertSame(2, $backends[0]['workers_count']);
            if ($expectedFailure) {
                self::assertSame('', $backends[1]['work_dir']);
                self::assertSame($this->configuration(self::BASE), $configurations[0]);
                self::assertFileDoesNotExist($state . '/fastpanel-applied.json');
            } else {
                self::assertSame(self::BASE . '/current/public', FastPanel::root($configurations[0]['frontend']));
                self::assertFileExists($state . '/server-profile.json');
            }
        } finally {
            foreach (['fastpanel-before.json', 'fastpanel-applied.json', 'server-profile.json'] as $name) {
                if (is_file($state . '/' . $name)) {
                    unlink($state . '/' . $name);
                }
            }
            rmdir($state);
        }
    }

    /** @return array{id: int, frontend: string, backend: string, phpini: string} */
    private function configuration(string $root): array
    {
        return ['id' => 7, 'frontend' => 'server {' . "\n    set \$root_path $root;\n"
            . '    location / { try_files $uri $uri/ /index.php?$args; }' . "\n"
            . '    location ~ \.php$ { include fastcgi_params; }' . "\n}",
            'backend' => "[demo.test]\nuser = demo\npm = dynamic\npm.max_children = 8\n"
                . "php_admin_value[max_execution_time] = 120\n", 'phpini' => ''];
    }
}
