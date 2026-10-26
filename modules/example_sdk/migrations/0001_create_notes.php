<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if (!$schema->hasTable('module_example_sdk_notes')) {
        $schema->create('module_example_sdk_notes', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title', 190);
            $table->timestamps();
        });
    }
};
