<?php

declare(strict_types=1);

namespace MajorDoMo\Api\Webhooks;

use InvalidArgumentException;
use MajorDoMo\Runtime\CodeRunner;
use MajorDoMo\Runtime\PhpSyntaxValidator;
use MajorDoMo\Scripts\ScriptRepository;
use MajorDoMo\Scripts\ScriptRunner;
use Throwable;

/**
 * Входящие webhook: секрет, проверки, запуск сценария/PHP.
 *
 * Секрет хранится только как SHA-256; в UI показывается один раз при
 * создании/ротации. Старый `HookStore` не используется.
 *
 * 2026-10-27 — PHP-код webhook проверяется синтаксически до сохранения.
 */
final readonly class WebhookService
{
    public const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public const HANDLERS = ['script', 'code'];

    public function __construct(
        private WebhookRepository $repository,
        private ScriptRepository $scripts,
        private ScriptRunner $scriptRunner,
        private CodeRunner $runner,
        private PhpSyntaxValidator $syntax
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listing(): array
    {
        return array_map(fn (array $row): array => $this->decorate($row), $this->repository->all());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = $this->repository->find($id);

        return $row === null ? null : $this->decorate($row);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id:int,secret:string}
     */
    public function create(array $input): array
    {
        $data = $this->validate($input);
        $secret = $this->secret();
        $now = date('Y-m-d H:i:s');
        $id = $this->repository->create([
            'name' => $data['name'],
            'secret_hash' => hash('sha256', $secret),
            'secret_prefix' => substr($secret, 0, 10),
            'handler_type' => $data['handler_type'],
            'script_id' => $data['script_id'],
            'code' => $data['code'],
            'methods' => json_encode($data['methods'], JSON_UNESCAPED_SLASHES) ?: '["POST"]',
            'enabled' => $data['enabled'] ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['id' => $id, 'secret' => $secret];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(int $id, array $input): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Webhook не найден.');
        }
        $data = $this->validate($input);
        $this->repository->update($id, [
            'name' => $data['name'],
            'handler_type' => $data['handler_type'],
            'script_id' => $data['script_id'],
            'code' => $data['code'],
            'methods' => json_encode($data['methods'], JSON_UNESCAPED_SLASHES) ?: '["POST"]',
            'enabled' => $data['enabled'] ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if ($data['handler_type'] === 'script') {
            $this->repository->update($id, ['code' => null]);
        } else {
            $this->repository->update($id, ['script_id' => null]);
        }
    }

    public function rotate(int $id): string
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Webhook не найден.');
        }
        $secret = $this->secret();
        $this->repository->update($id, [
            'secret_hash' => hash('sha256', $secret),
            'secret_prefix' => substr($secret, 0, 10),
            'last_used_at' => null,
            'last_status' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $secret;
    }

    public function delete(int $id): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Webhook не найден.');
        }
        $this->repository->delete($id);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function handle(string $secret, string $method, array $payload, array $headers = []): mixed
    {
        if (!preg_match('/^[a-f0-9]{48}$/D', $secret)) {
            throw new WebhookException(404, 'not_found', 'Вход не найден.');
        }
        $record = $this->repository->findBySecretHash(hash('sha256', $secret));
        if ($record === null) {
            throw new WebhookException(404, 'not_found', 'Вход не найден.');
        }
        $methods = $this->methods($record);
        if (!in_array($method, $methods, true)) {
            throw new WebhookException(405, 'method_not_allowed', 'Метод не разрешён.', $methods);
        }
        if ((int)($record['enabled'] ?? 0) !== 1) {
            throw new WebhookException(403, 'disabled', 'Вход выключен.');
        }

        try {
            $params = [
                'payload' => $payload,
                'method' => $method,
                'headers' => $headers,
                'webhook' => $record['name'] ?? '',
            ];
            $result = ($record['handler_type'] ?? '') === 'script'
                ? $this->scriptRunner->run((int)($record['script_id'] ?? 0), $params)
                : $this->runner->run((string)($record['code'] ?? ''), $params);
            $this->repository->touch((int)$record['id'], 200);

            return $result;
        } catch (Throwable $error) {
            $this->repository->touch((int)$record['id'], 500);
            error_log('MyDoMo webhook failed: ' . $error->getMessage());

            throw new WebhookException(500, 'handler_failed', 'Обработчик завершился с ошибкой.');
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decorate(array $row): array
    {
        unset($row['secret_hash']);
        $row['methods'] = $this->methods($row);
        $row['enabled'] = (int)($row['enabled'] ?? 0) === 1;
        $row['secret_preview'] = (string)($row['secret_prefix'] ?? '') . '…';
        $row['handler_label'] = ($row['handler_type'] ?? '') === 'script' ? 'Сценарий' : 'PHP-код';
        $row['last_status'] = $row['last_status'] === null ? null : (int)$row['last_status'];

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return list<string>
     */
    private function methods(array $row): array
    {
        $decoded = json_decode((string)($row['methods'] ?? '["POST"]'), true);
        if (!is_array($decoded)) {
            return ['POST'];
        }
        $methods = [];
        foreach ($decoded as $method) {
            if (is_string($method)) {
                $method = strtoupper($method);
                if (in_array($method, self::METHODS, true) && !in_array($method, $methods, true)) {
                    $methods[] = $method;
                }
            }
        }

        return $methods === [] ? ['POST'] : $methods;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{name:string,handler_type:string,script_id:int|null,code:string|null,methods:list<string>,enabled:bool}
     */
    private function validate(array $input): array
    {
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 190) {
            throw new InvalidArgumentException('Название webhook обязательно (до 190 символов).');
        }

        $handler = (string)($input['handler_type'] ?? 'script');
        if (!in_array($handler, self::HANDLERS, true)) {
            throw new InvalidArgumentException('Неизвестный тип обработчика.');
        }

        $scriptId = null;
        $code = null;
        if ($handler === 'script') {
            $scriptId = (int)($input['script_id'] ?? 0);
            if ($scriptId <= 0 || $this->scripts->find($scriptId) === null) {
                throw new InvalidArgumentException('Выберите существующий сценарий.');
            }
        } else {
            $code = trim((string)($input['code'] ?? ''));
            if ($code === '') {
                throw new InvalidArgumentException('PHP-код не может быть пустым.');
            }
            $syntaxError = $this->syntax->validate($code);
            if ($syntaxError !== null) {
                throw new InvalidArgumentException(
                    'Синтаксическая ошибка в строке ' . $syntaxError['line'] . ': ' . $syntaxError['message']
                );
            }
        }

        $rawMethods = $input['methods'] ?? ['POST'];
        $rawMethods = is_array($rawMethods) ? $rawMethods : [$rawMethods];
        $methods = [];
        foreach ($rawMethods as $method) {
            $method = strtoupper(trim((string)$method));
            if (in_array($method, self::METHODS, true) && !in_array($method, $methods, true)) {
                $methods[] = $method;
            }
        }
        if ($methods === []) {
            throw new InvalidArgumentException('Разрешите хотя бы один HTTP-метод.');
        }

        return [
            'name' => $name,
            'handler_type' => $handler,
            'script_id' => $scriptId,
            'code' => $code,
            'methods' => $methods,
            'enabled' => !empty($input['enabled']),
        ];
    }

    private function secret(): string
    {
        return bin2hex(random_bytes(24));
    }
}
