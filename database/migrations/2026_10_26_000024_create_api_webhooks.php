<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * Входящие webhook теперь ядровая таблица `api_webhooks`.
 *
 * Если на стенде осталась таблица старого core-модуля `module_api_webhooks`,
 * она переименовывается один раз; runtime-адаптера нет.
 */
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('api_webhooks')) {
        return;
    }
    if ($schema->hasTable('module_api_webhooks')) {
        $schema->rename('module_api_webhooks', 'api_webhooks');

        return;
    }

    $schema->create('api_webhooks', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name', 190);
        $table->string('secret_hash', 64)->unique();
        $table->string('secret_prefix', 12);
        $table->string('handler_type', 16)->default('script');
        $table->integer('script_id')->nullable();
        $table->text('code')->nullable();
        $table->string('methods', 100)->default('["POST"]');
        $table->boolean('enabled')->default(true);
        $table->dateTime('last_used_at')->nullable();
        $table->integer('last_status')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->index(['enabled', 'id']);
    });
};
