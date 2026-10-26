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
        $table->increments('id');
        $table->string('topic', 191)->unique();
        $table->string('module_id', 64);
        $table->text('payload');
        $table->dateTime('expires_at');
        $table->timestamps();
        $table->softDeletes();
        $table->index('expires_at');
    });
};
