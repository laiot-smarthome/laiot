<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

// 2026-10-27 — у сценария появился выбор окружения: с обёртками ядра
// (по умолчанию) или как обычный PHP-файл без них.
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if (!$schema->hasTable('scripts') || $schema->hasColumn('scripts', 'use_core')) {
        return;
    }

    $schema->table('scripts', static function (Blueprint $table): void {
        $table->boolean('use_core')->default(true)->after('event_name');
    });
};
