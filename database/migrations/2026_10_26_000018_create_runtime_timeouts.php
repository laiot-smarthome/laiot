<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('runtime_timeouts')) {
        return;
    }

    $schema->create('runtime_timeouts', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('title', 191);
        $table->text('code');
        $table->dateTime('run_at');
        $table->dateTime('executed_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->index(['run_at', 'executed_at', 'deleted_at']);
    });
};
