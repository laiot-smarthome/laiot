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
        $table->increments('ID');
        $table->string('QUEUE', 64);
        $table->string('JOB', 191);
        $table->text('PAYLOAD');
        $table->integer('ATTEMPTS')->default(0);
        $table->dateTime('AVAILABLE_AT');
        $table->dateTime('RESERVED_AT')->nullable();
        $table->dateTime('FAILED_AT')->nullable();
        $table->text('ERROR')->nullable();
        $table->dateTime('CREATED_AT');
        $table->index(['QUEUE', 'FAILED_AT', 'AVAILABLE_AT']);
    });
};
