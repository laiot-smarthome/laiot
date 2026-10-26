<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('property_history')) {
        return;
    }

    $schema->create('property_history', static function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('object_id');
        $table->integer('property_id');
        $table->text('value')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->index(['object_id', 'property_id', 'created_at']);
    });
};
