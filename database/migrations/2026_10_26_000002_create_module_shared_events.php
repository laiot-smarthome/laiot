<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('module_shared_events')) {
        return;
    }

    $schema->create('module_shared_events', static function (Blueprint $table): void {
        $table->string('TOPIC', 191)->primary();
        $table->string('MODULE_ID', 64);
        $table->text('PAYLOAD');
        $table->dateTime('UPDATED_AT');
        $table->dateTime('EXPIRES_AT');
        $table->index('EXPIRES_AT');
    });
};
