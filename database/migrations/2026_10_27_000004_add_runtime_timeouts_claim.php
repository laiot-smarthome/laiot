<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * Аренда таймера setTimeOut.
 *
 * 2026-10-27 — два процесса таймеров не должны выполнить одну запись:
 *              добавлены claimed_at и claim_token, как у очереди заданий.
 */
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if (!$schema->hasTable('runtime_timeouts')
        || $schema->hasColumn('runtime_timeouts', 'claim_token')) {
        return;
    }

    $schema->table('runtime_timeouts', static function (Blueprint $table): void {
        $table->dateTime('claimed_at')->nullable();
        $table->string('claim_token', 32)->nullable();
    });
};
