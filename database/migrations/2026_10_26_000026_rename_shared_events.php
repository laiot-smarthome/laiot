<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * `module_shared_events` был ядровой шиной с префиксом модуля. Теперь это
 * ядровая таблица `shared_events`; существующая переименовывается один раз.
 */
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('shared_events')) {
        return;
    }
    if ($schema->hasTable('module_shared_events')) {
        $schema->rename('module_shared_events', 'shared_events');

        return;
    }

    $schema->create('shared_events', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('topic', 191)->unique();
        $table->string('module_id', 64);
        $table->text('payload');
        $table->dateTime('expires_at');
        $table->timestamps();
        $table->softDeletes();
        $table->index(['module_id', 'expires_at']);
    });
};
