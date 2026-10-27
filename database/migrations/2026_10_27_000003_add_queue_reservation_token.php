<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

// 2026-10-27 — устаревший worker не должен подтверждать чужую резервацию.
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('queue_jobs') && !$schema->hasColumn('queue_jobs', 'reservation_token')) {
        $schema->table('queue_jobs', static function (Blueprint $table): void {
            $table->string('reservation_token', 32)->nullable();
        });
    }
};
