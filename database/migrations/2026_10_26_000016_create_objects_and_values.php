<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();

    if (!$schema->hasTable('objects')) {
        $schema->create('objects', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title', 191)->unique();
            $table->integer('class_id')->default(0);
            $table->integer('location_id')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['class_id', 'location_id']);
        });
    }

    if (!$schema->hasTable('property_values')) {
        $schema->create('property_values', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('object_id');
            $table->integer('property_id');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['object_id', 'property_id']);
        });
    }
};
