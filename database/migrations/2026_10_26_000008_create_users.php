<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('users')) {
        return;
    }

    $schema->create('users', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('username', 100)->unique();
        $table->string('name', 191)->default('');
        $table->string('password', 255);
        $table->boolean('is_admin')->default(false);
        $table->boolean('is_default')->default(false);
        $table->timestamps();
        $table->softDeletes();
    });
};
