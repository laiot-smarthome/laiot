<?php

declare(strict_types=1);

namespace MajorDoMo\Settings;

use MajorDoMo\Foundation\Config;
use RuntimeException;

/**
 * Системные настройки хранятся в окружении; таблица settings здесь не участвует.
 *
 * 2026-10-27 — чтение, проверка типов и запись только разрешённого раздела .env.
 */
final readonly class SettingsService
{
    /** @param array<string, mixed> $defaults */
    public function __construct(
        private EnvFile $file,
        private array $defaults,
        private DatabaseSettingsGuard $databaseGuard
    ) {
    }

    /** @return array{revision:string, fields:array<string, array<string, mixed>>} */
    public function snapshot(): array
    {
        $contents = $this->file->contents();
        $values = Config::parse($contents);
        $fields = [];
        foreach (SettingsSchema::fields() as $key => $field) {
            $external = getenv($key);
            $value = $external !== false ? $external : ($values[$key] ?? $this->defaults[$key]);
            $secret = $field['type'] === 'secret';
            $fields[$key] = $field + [
                'value' => $secret ? '' : $this->display($value, (string)$field['type']),
                'configured' => $secret && (string)$value !== '',
                'external' => $external !== false,
                'source' => $external !== false ? 'Окружение процесса' : (array_key_exists($key, $values) ? '.env' : 'По умолчанию'),
            ];
        }

        return ['revision' => hash('sha256', $contents), 'fields' => $fields];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $clear
     * @param array<string, mixed> $user
     */
    public function save(string $group, array $input, array $clear, string $revision, array $user): void
    {
        if (!isset(SettingsSchema::groups()[$group])) {
            throw new RuntimeException('Неизвестный раздел настроек.');
        }
        $contents = $this->file->contents();
        if (!hash_equals(hash('sha256', $contents), $revision)) {
            throw new RuntimeException('Настройки уже изменены. Обновите страницу и повторите изменения.');
        }
        $fields = array_filter(SettingsSchema::fields(), static fn (array $field): bool => $field['group'] === $group);
        if (array_diff_key($input, $fields) !== [] || array_diff_key($clear, $fields) !== []) {
            throw new RuntimeException('Форма содержит неизвестные параметры или параметры другого раздела.');
        }
        $values = Config::parse($contents);
        $changes = [];
        $errors = [];
        foreach ($fields as $key => $field) {
            $external = getenv($key);
            if ($external !== false) {
                if (array_key_exists($key, $input) || array_key_exists($key, $clear)) {
                    $errors[$key] = 'Параметр задан окружением процесса. Измените его в настройках службы или контейнера.';
                }
                continue;
            }
            $secret = $field['type'] === 'secret';
            $raw = $input[$key] ?? ($secret ? '' : null);
            if (!is_string($raw) || strlen($raw) > 4096 || preg_match('/[\x00-\x1F\x7F]/', $raw) === 1) {
                $errors[$key] = 'Введите однострочное значение без управляющих символов, не более 4096 байт.';
                continue;
            }
            $value = $secret ? $raw : trim($raw);
            if ($secret) {
                $erase = $clear[$key] ?? '0';
                if (!in_array($erase, ['0', '1'], true)) {
                    $errors[$key] = 'Некорректный флажок удаления пароля.';
                    continue;
                }
                if ($erase === '1' && $value !== '') {
                    $errors[$key] = 'Укажите новый пароль или удаление прежнего, а не оба действия.';
                    continue;
                }
                if ($value === '' && $erase !== '1') {
                    continue;
                }
            } elseif (isset($clear[$key])) {
                $errors[$key] = 'Удаление значения доступно только для пароля.';
                continue;
            }
            if (($field['required'] ?? false) && $value === '') {
                $errors[$key] = 'Заполните это поле.';
            } elseif ($field['type'] === 'int' && (filter_var($value, FILTER_VALIDATE_INT) === false
                || (int)$value < (int)$field['min'] || (int)$value > (int)$field['max'])) {
                $errors[$key] = 'Введите целое число от ' . $field['min'] . ' до ' . $field['max'] . '.';
            } elseif ($field['type'] === 'bool' && !in_array($value, ['0', '1'], true)) {
                $errors[$key] = 'Выберите «Включено» или «Выключено».';
            } elseif ($field['type'] === 'select' && !array_key_exists($value, (array)$field['options'])) {
                $errors[$key] = 'Выберите один из поддерживаемых вариантов.';
            } elseif (in_array($key, ['DB_HOST', 'DB_NAME'], true) && str_contains($value, ';')) {
                $errors[$key] = 'Точка с запятой в адресе или имени базы не поддерживается.';
            }
            $normalized = $field['type'] === 'int' ? (string)(int)$value : $value;
            $current = $values[$key] ?? $this->display($this->defaults[$key], (string)$field['type']);
            if ($normalized !== $current) {
                $changes[$key] = $normalized;
            }
        }
        if ($group === 'queue' && $errors === []) {
            $effective = $this->effective($values, $changes);
            if ((int)$effective['QUEUE_RESERVATION_TIMEOUT'] <= (int)$effective['QUEUE_HANDLER_TIMEOUT']) {
                $errors['QUEUE_RESERVATION_TIMEOUT'] = 'Аренда задания должна быть больше лимита обработчика.';
            }
        }
        if ($errors !== []) {
            throw new SettingsValidationException($errors);
        }
        if ($changes === []) {
            return;
        }
        if ($group === 'database') {
            $this->databaseGuard->verify($this->effective($values, $changes), $user);
        }
        $this->file->save($changes, $revision);
    }

    /**
     * @param array<string, string> $values
     * @param array<string, string> $changes
     * @return array<string, string>
     */
    private function effective(array $values, array $changes): array
    {
        $effective = [];
        foreach (SettingsSchema::fields() as $key => $field) {
            $external = getenv($key);
            $effective[$key] = $external !== false ? $external : ($changes[$key] ?? $values[$key] ?? $this->display($this->defaults[$key], (string)$field['type']));
        }

        return $effective;
    }

    private function display(mixed $value, string $type): string
    {
        if ($type === 'bool') {
            return in_array(strtolower((string)$value), ['1', 'true', 'on', 'yes'], true) ? '1' : '0';
        }

        return (string)$value;
    }
}
