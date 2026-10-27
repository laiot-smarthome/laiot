<?php

declare(strict_types=1);

namespace MajorDoMo\Modules;

use InvalidArgumentException;

/**
 * Настройки модуля, объявленные в манифесте.
 *
 * Схема полей приходит из `module.json`, а значения — из
 * `module_registry.settings`. Модуль читает их через
 * `$context->settings()->get('host')`, общий экран админки сохраняет их
 * через `cast()`: типы, значения select и умолчания проверяются в одном
 * месте, а не в каждом модуле по-своему.
 *
 * 2026-10-27 — настройки модулей из манифеста вместо пустой колонки реестра.
 */
final readonly class ModuleSettings
{
    /**
     * @param array<string, array{title:string,type:string,default:mixed,options:array<string,string>,description:string}> $schema
     * @param array<string, mixed> $values
     */
    public function __construct(
        private array $schema,
        private array $values
    ) {
    }

    /**
     * Действующие значения: сохранённые поверх умолчаний схемы.
     *
     * @param array<string, array{title:string,type:string,default:mixed,options:array<string,string>,description:string}> $schema
     * @param array<string, mixed> $stored
     */
    public static function fromStored(array $schema, array $stored): self
    {
        $values = [];
        foreach ($schema as $key => $field) {
            $values[$key] = array_key_exists($key, $stored) ? $stored[$key] : $field['default'];
        }

        return new self($schema, $values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * @return array<string, array{title:string,type:string,default:mixed,options:array<string,string>,description:string}>
     */
    public function schema(): array
    {
        return $this->schema;
    }

    /**
     * Приводит отправленные значения к типам схемы.
     *
     * Лишние поля игнорируются: форма может прислать служебные ключи, но
     * сохраняются только объявленные настройки. Неизвестное значение select
     * и нецелое число — ошибка, а не молчаливая замена умолчанием.
     *
     * @param array<string, array{title:string,type:string,default:mixed,options:array<string,string>,description:string}> $schema
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function cast(array $schema, array $input): array
    {
        $values = [];
        foreach ($schema as $key => $field) {
            $raw = $input[$key] ?? null;
            $values[$key] = match ($field['type']) {
                'int' => self::integer($field, $raw),
                'bool' => in_array($raw, [true, 1, '1', 'on', 'true', 'yes'], true),
                'select' => self::option($field, $raw),
                default => self::text($field, $raw),
            };
        }

        return $values;
    }

    /**
     * @param array{title:string,type:string,default:mixed,options:array<string,string>,description:string} $field
     */
    private static function integer(array $field, mixed $raw): int
    {
        if ($raw === null || $raw === '') {
            return (int)$field['default'];
        }
        if (!is_scalar($raw) || filter_var((string)$raw, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('«' . $field['title'] . '»: нужно целое число.');
        }

        return (int)$raw;
    }

    /**
     * @param array{title:string,type:string,default:mixed,options:array<string,string>,description:string} $field
     */
    private static function option(array $field, mixed $raw): string
    {
        if ($raw === null) {
            return (string)$field['default'];
        }
        $value = is_scalar($raw) ? (string)$raw : '';
        if (!array_key_exists($value, $field['options'])) {
            throw new InvalidArgumentException('«' . $field['title'] . '»: недопустимое значение.');
        }

        return $value;
    }

    /**
     * @param array{title:string,type:string,default:mixed,options:array<string,string>,description:string} $field
     */
    private static function text(array $field, mixed $raw): string
    {
        if ($raw !== null && !is_scalar($raw)) {
            throw new InvalidArgumentException('«' . $field['title'] . '»: ожидается строка.');
        }
        if ($raw === null) {
            return (string)$field['default'];
        }

        return trim((string)$raw);
    }
}
