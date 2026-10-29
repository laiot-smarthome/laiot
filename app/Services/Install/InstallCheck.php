<?php

declare(strict_types=1);

namespace LaIOT\Services\Install;

/**
 * Результат одной проверки установщика.
 *
 * Проверки не бросают исключения: страница и CLI показывают весь список,
 * чтобы администратор видел сразу все проблемы окружения, а не первую.
 * `fail` блокирует установку, `warn` — только предупреждение.
 *
 * 2026-10-28 — проверки отделены от применения, чтобы страница установки
 *              работала без настроенной базы данных.
 */
final readonly class InstallCheck
{
    public const OK = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    public function __construct(
        public string $id,
        public string $label,
        public string $status,
        public string $message = ''
    ) {
    }

    public static function ok(string $id, string $label, string $message = ''): self
    {
        return new self($id, $label, self::OK, $message);
    }

    public static function warn(string $id, string $label, string $message = ''): self
    {
        return new self($id, $label, self::WARN, $message);
    }

    public static function fail(string $id, string $label, string $message = ''): self
    {
        return new self($id, $label, self::FAIL, $message);
    }

    public function failed(): bool
    {
        return $this->status === self::FAIL;
    }

    /**
     * @return array{id:string,label:string,status:string,message:string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
