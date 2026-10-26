<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('settings')) {
        return;
    }

    $schema->create('settings', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name', 100)->unique();
        $table->text('value')->nullable();
        $table->string('title', 191)->default('');
        $table->string('type', 32)->default('text');
        $table->timestamps();
        $table->softDeletes();
    });
};
