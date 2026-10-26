<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('locations')) {
        return;
    }

    $schema->create('locations', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('title', 191);
        $table->integer('priority')->default(0);
        $table->timestamps();
        $table->softDeletes();
        $table->index(['priority', 'title']);
    });
};
