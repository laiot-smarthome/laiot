<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * Центр уведомлений админки MyDoMo.
 *
 * Одна строка — одно непрочитанное (или уже прочитанное) уведомление модуля.
 * `read_at` вместо булева флага: время прочтения полезно для журнала, а
 * выборка непрочитанных остаётся простой (`read_at is null`).
 */
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('notifications')) {
        return;
    }

    $schema->create('notifications', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('module', 100)->default('');
        $table->string('type', 20)->default('info');
        $table->string('message', 255);
        $table->string('title', 200)->nullable();
        $table->text('details')->nullable();
        $table->string('url', 2048)->nullable();
        $table->string('label', 100)->nullable();
        $table->string('notification_key', 190)->nullable();
        $table->dateTime('read_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->index(['module', 'read_at']);
        $table->index('read_at');
        $table->index('notification_key');
    });
};
