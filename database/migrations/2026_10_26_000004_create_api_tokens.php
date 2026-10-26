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
        $table->increments('id');
        $table->integer('user_id');
        $table->string('name', 191);
        $table->string('token_hash', 64)->unique();
        $table->dateTime('expires_at')->nullable();
        $table->dateTime('last_used_at')->nullable();
        $table->dateTime('revoked_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->index(['user_id', 'revoked_at']);
    });
};
