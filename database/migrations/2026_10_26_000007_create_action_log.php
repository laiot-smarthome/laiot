<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('action_log')) {
        return;
    }

    $schema->create('action_log', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('type', 100)->default('');
        $table->string('title', 255)->default('');
        $table->string('user_name', 255)->default('');
        $table->string('terminal', 255)->default('');
        $table->string('ip', 100)->default('');
        $table->timestamps();
        $table->softDeletes();
        $table->index(['type', 'created_at']);
    });
};
