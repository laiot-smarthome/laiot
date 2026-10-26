<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('properties')) {
        return;
    }

    $schema->create('properties', static function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('class_id')->default(0);
        $table->string('title', 191);
        $table->text('description')->nullable();
        $table->string('data_type', 32)->default('string');
        $table->boolean('keep_history')->default(false);
        $table->string('on_change', 255)->default('');
        $table->timestamps();
        $table->softDeletes();
        $table->index(['class_id', 'title']);
    });
};
