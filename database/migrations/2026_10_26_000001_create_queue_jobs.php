<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('queue_jobs')) {
        return;
    }

    $schema->create('queue_jobs', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('queue', 64);
        $table->string('job', 191);
        $table->text('payload');
        $table->integer('attempts')->default(0);
        $table->dateTime('available_at');
        $table->dateTime('reserved_at')->nullable();
        $table->dateTime('failed_at')->nullable();
        $table->text('error')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->index(['queue', 'failed_at', 'available_at']);
    });
};
