<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('system_errors')) {
        return;
    }

    $schema->create('system_errors', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('level', 16)->default('error');
        $table->text('message');
        $table->text('context')->nullable();
        $table->string('request_id', 64)->default('');
        $table->timestamps();
        $table->softDeletes();
        $table->index(['level', 'created_at']);
    });
};
