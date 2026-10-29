<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

// 2026-10-29 — важность хранится с записью для маршрутизации и повышения при повторе.
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if (!$schema->hasColumn('notifications', 'importance')) {
        $schema->table('notifications', static function (Blueprint $table): void {
            $table->unsignedTinyInteger('importance')->default(5);
        });
    }
};
