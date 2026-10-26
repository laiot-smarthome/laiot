<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('classes')) {
        return;
    }

    $schema->create('classes', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('title', 191)->unique();
        $table->timestamps();
        $table->softDeletes();
    });
};
