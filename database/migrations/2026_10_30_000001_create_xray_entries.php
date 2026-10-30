<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

// 2026-10-30 — независимый трёхсуточный журнал диагностики, без связи с keep_history.
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if (!$schema->hasTable('xray_entries')) {
        $schema->create('xray_entries', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('kind', 16);
            $table->string('name', 191);
            $table->string('source', 500)->default('');
            $table->longText('payload');
            $table->dateTime('created_at');
            $table->index(['kind', 'id']);
            $table->index('created_at');
        });
    }
};
