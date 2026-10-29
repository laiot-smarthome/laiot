<?php

declare(strict_types=1);

namespace LaIOT\Deployment;

use Closure;
use RuntimeException;
use Throwable;

/**
 * Системный SSH-мастер: меняет только выбранный сайт через локальный API панели.
 * Не загружает приложение, .env и подключения к его сервисам.
 * 2026-10-29 — публичный каталог, лимиты FPM, nginx и откат конфигурации.
 */
final class FastPanel
{
    private string $pin = '';

    public function __construct(private readonly string $state, private readonly ?Closure $transport = null)
    {
        if ($transport !== null) {
            return;
        }
        // Самоподписанный сертификат доверяется только локально и закрепляется
        // по публичному ключу. Токен никогда не отправляется внешнему хосту.
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false,
        ]]);
        $socket = stream_socket_client('ssl://127.0.0.1:8888', $errno, $error, 10, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new RuntimeException('Локальный HTTPS FastPanel недоступен: ' . $error);
        }
        $options = stream_context_get_options($socket);
        fclose($socket);
        $key = openssl_pkey_get_public($options['ssl']['peer_certificate']);
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if ($details === false) {
            throw new RuntimeException('Не удалось закрепить TLS-ключ FastPanel.');
        }
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s/', '', $details['key']), true);
        if ($der === false) {
            throw new RuntimeException('Некорректный TLS-ключ FastPanel.');
        }
        $this->pin = 'sha256//' . base64_encode(hash('sha256', $der, true));
    }

    public function configure(int $id, string $public, string $version, int $memoryMiB, int $cpus): void
    {
        if ($id < 1 || !preg_match('~^/[a-zA-Z0-9_./-]+/current/public$~D', $public)
            || !preg_match('/^[0-9]+\.[0-9]+$/D', $version)) {
            throw new RuntimeException('Некорректные параметры сайта.');
        }
        $site = $this->request('GET', "/sites/$id")['data'];
        $before = $this->request('GET', "/sites/$id/configuration")['data'];
        $base = rtrim($site['index_dir'], '/');
        if (!str_starts_with($public, $base . '/') || str_contains($public, '/..')) {
            throw new RuntimeException('Приложение должно находиться внутри каталога выбранного сайта FastPanel.');
        }
        $originalBackend = $site['main_backend'];
        // API PHP-сайтов возвращает пустой work_dir: прежний путь берём из
        // фактического nginx, чтобы откат вернул и нестандартный подкаталог.
        $oldRoot = self::root($before['frontend']);
        if ($oldRoot !== $base && !str_starts_with($oldRoot, $base . '/')) {
            throw new RuntimeException('Нестандартный nginx: безопасный автоматический откат невозможен.');
        }
        $originalBackend['work_dir'] = $oldRoot === $base ? '' : substr($oldRoot, strlen($base) + 1);
        $this->save('fastpanel-before.json', ['site' => $site, 'configuration' => $before]);
        $backend = $originalBackend;
        $backend['handler'] = 'php_fpm';
        $backend['handler_version'] = str_replace('.', '', $version);
        $backend['app_file'] = 'index.php';
        $backend['work_dir'] = substr($public, strlen($base) + 1);
        $children = self::children($memoryMiB, $cpus);
        $backend['workers_count'] = $children;
        try {
            $this->request('PUT', '/sites/backend/' . (int) $backend['id'], $backend);
            $configuration = $this->waitForRoot($id, $public);
            $configuration['backend'] = self::fpm($configuration['backend'], $children);
            $configuration['frontend'] = self::nginx($configuration['frontend']);
            $this->request('PUT', "/sites/$id/configuration", $configuration);
            $this->save('fastpanel-applied.json', $configuration);
            $this->save('server-profile.json', ['ram_mib' => $memoryMiB, 'cpus' => $cpus,
                'php' => $version, 'fpm_children' => $children, 'request_seconds' => 600]);
        } catch (Throwable $error) {
            try {
                $this->request('PUT', '/sites/backend/' . (int) $originalBackend['id'], $originalBackend);
                $this->waitForRoot($id, $oldRoot);
                $this->request('PUT', "/sites/$id/configuration", $before);
            } catch (Throwable $rollback) {
                throw new RuntimeException($error->getMessage() . '; откат FastPanel не завершён: '
                    . $rollback->getMessage() . '. Снимок: ' . $this->state . '/fastpanel-before.json', 0, $error);
            }
            throw new RuntimeException('Настройка сайта отменена, прежняя конфигурация возвращена: ' . $error->getMessage(), 0, $error);
        }
    }

    public static function children(int $memoryMiB, int $cpus): int
    {
        // Оставляем память системе, панели, БД и фоновым процессам. Два FPM
        // позволяют открыть интерфейс, пока второй процесс ждёт помощника.
        $reserve = max(512, (int) ceil($memoryMiB * 0.5));
        return max(2, min(32, max(1, $cpus) * 4, (int) floor(($memoryMiB - $reserve) / 192)));
    }

    public static function root(string $frontend): string
    {
        preg_match_all('/set\s+\$root_path\s+([^;\s]+)\s*;/', $frontend, $matches);
        $paths = array_unique($matches[1]);
        if (count($paths) !== 1) {
            throw new RuntimeException('Не распознан корень nginx выбранного сайта.');
        }
        return trim(reset($paths), '"\'');
    }

    public static function fpm(string $configuration, int $children): string
    {
        preg_match_all('/^\[[^\r\n]+\]\s*$/m', $configuration, $pools);
        if (count($pools[0]) !== 1) {
            throw new RuntimeException('Ожидался один выделенный пул PHP-FPM.');
        }
        $values = ['pm' => 'dynamic', 'pm.max_children' => (string) $children,
            'pm.start_servers' => '1', 'pm.min_spare_servers' => '1',
            'pm.max_spare_servers' => (string) min(2, $children), 'pm.max_requests' => '500',
            'request_terminate_timeout' => '660s', 'security.limit_extensions' => '.php',
            'php_admin_value[max_execution_time]' => '600', 'php_admin_value[max_input_time]' => '60',
            'php_admin_value[default_socket_timeout]' => '600', 'php_admin_value[memory_limit]' => '256M',
            'php_admin_value[display_errors]' => 'off', 'php_admin_value[log_errors]' => 'on'];
        foreach ($values as $key => $value) {
            $line = $key . ' = ' . $value;
            $configuration = preg_replace('/^\s*' . preg_quote($key, '/') . '\s*=.*$/m', $line, $configuration, -1, $count);
            if ($count === 0) {
                $configuration = rtrim($configuration) . "\n" . $line . "\n";
            }
        }
        return $configuration;
    }

    public static function nginx(string $frontend): string
    {
        if (!str_contains($frontend, 'try_files $uri $uri/ /index.php?')) {
            throw new RuntimeException('Нестандартная маршрутизация nginx: автоматическая замена небезопасна.');
        }
        if (str_contains($frontend, '# LAIOT BEGIN')) {
            return $frontend;
        }
        $frontend = str_replace('location ~ \.php$ {', 'location = /index.php {', $frontend, $count);
        if ($count === 0) {
            throw new RuntimeException('Не распознан обработчик PHP FastPanel.');
        }
        $managed = <<<'NGINX'
    # LAIOT BEGIN — служебные файлы закрыты, PHP исполняет только front controller.
    location ~ (^|/)\.(?!well-known(?:/|$)) { return 404; }
    location ~* \.php(?:/|$) { return 404; }
    server_tokens off;
    fastcgi_connect_timeout 10s;
    fastcgi_read_timeout 660s;
    fastcgi_send_timeout 660s;
    location = /websocket {
        proxy_pass http://127.0.0.1:8081;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 660s;
    }
    # LAIOT END

NGINX;
        return str_replace('    location = /index.php {', $managed . '    location = /index.php {', $frontend);
    }

    private function waitForRoot(int $id, string $expected): array
    {
        for ($attempt = 0; $attempt < 40; $attempt++) {
            try {
                $configuration = $this->request('GET', "/sites/$id/configuration")['data'];
                if (self::root($configuration['frontend']) === $expected) {
                    return $configuration;
                }
            } catch (RuntimeException $error) {
                // После принятого PUT панель временно отвечает 400, пока
                // перестраивает конфигурацию. Остальные ошибки не скрываем.
                if ($error->getCode() !== 400) {
                    throw $error;
                }
            }
            usleep(250000);
        }
        throw new RuntimeException('FastPanel не применила новый публичный каталог.');
    }

    private function save(string $name, array $data): void
    {
        $path = $this->state . '/' . $name;
        if (is_link($path) || file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)) === false
            || !chmod($path, 0600)) {
            throw new RuntimeException('Не удалось сохранить снимок ' . $name);
        }
    }

    private function request(string $method, string $path, ?array $data = null): array
    {
        if ($this->transport !== null) {
            return ($this->transport)($method, $path, $data);
        }
        $token = json_decode(file_get_contents($this->state . '/panel-api-token.json'), true, flags: JSON_THROW_ON_ERROR)['msg'];
        if (!is_string($token) || $token === '' || str_contains($token, "\n")) {
            throw new RuntimeException('Некорректный временный токен FastPanel.');
        }
        $curl = curl_init('https://127.0.0.1:8888/api' . $path);
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 45, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_PINNEDPUBLICKEY => $this->pin,
            CURLOPT_PROXY => '', CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json']]);
        if ($data !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data, JSON_THROW_ON_ERROR));
        }
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($body === false || $status < 200 || $status >= 300) {
            throw new RuntimeException("FastPanel API $method $path: HTTP $status $error", $status);
        }
        $response = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($response)) {
            throw new RuntimeException('Пустой ответ API FastPanel.');
        }
        return $response;
    }
}
