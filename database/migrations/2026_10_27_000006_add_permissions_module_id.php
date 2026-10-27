<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * Владелец разрешения модуля.
 *
 * 2026-10-27 — колонка module_id: разрешения из манифеста можно показать в
 *              ролях и удалить вместе с модулем, не задев чужие.
 */
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if (!$schema->hasTable('permissions')
        || $schema->hasColumn('permissions', 'module_id')) {
        return;
    }

    $schema->table('permissions', static function (Blueprint $table): void {
        $table->string('module_id', 64)->nullable();
        $table->index('module_id');
    });
};
