<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * Ссылка на отдельный файл ошибки.
 *
 * 2026-10-27 — файл на каждую ошибку: колонка log_file в system_errors.
 */
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if (!$schema->hasTable('system_errors')
        || $schema->hasColumn('system_errors', 'log_file')) {
        return;
    }

    $schema->table('system_errors', static function (Blueprint $table): void {
        $table->string('log_file', 191)->nullable();
    });
};
