<?php

declare(strict_types=1);

namespace LaIOT\Services\Install;

/**
 * Итог успешной установки для страницы и CLI.
 *
 * Ничего секретного здесь нет: пароли не попадают в отчёт и не пишутся
 * в журнал. Список миграций нужен только для сообщения об успехе, а
 * предупреждения — для случаев, когда установка завершена, но что-то
 * осталось сделать вручную.
 *
 * 2026-10-28 — отчёт установки.
 */
final readonly class InstallReport
{
    /**
     * @param list<string> $migrations
     * @param list<string> $warnings
     */
    public function __construct(
        public string $version,
        public int $adminId,
        public string $adminLogin,
        public array $migrations,
        public string $installedAt,
        public string $cacheDriver,
        public array $warnings = []
    ) {
    }

    public function migrationsCount(): int
    {
        return count($this->migrations);
    }
}
