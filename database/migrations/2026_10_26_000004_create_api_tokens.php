<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('api_tokens')) {
        return;
    }

    $schema->create('api_tokens', static function (Blueprint $table): void {
        $table->increments('ID');
        $table->integer('USER_ID');
        $table->string('NAME', 191);
        $table->string('TOKEN_HASH', 64)->unique();
        $table->dateTime('CREATED_AT');
        $table->dateTime('EXPIRES_AT')->nullable();
        $table->dateTime('LAST_USED_AT')->nullable();
        $table->dateTime('REVOKED_AT')->nullable();
        $table->index(['USER_ID', 'REVOKED_AT']);
    });
};
