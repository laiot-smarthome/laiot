<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

// 2026-10-29 — outbox свойств и системных событий, тикеты WebSocket.
// Событие свойства пишется в одной транзакции со значением; системное
// событие — наблюдателем SystemEvents.
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();

    if (!$schema->hasTable('websocket_events')) {
        $schema->create('websocket_events', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            // property — изменение свойства, system — system.* / module.<id>.*
            $table->string('type', 16)->default('property');
            $table->unsignedBigInteger('object_id')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->string('object_title', 191)->default('');
            $table->string('property_title', 191)->default('');
            $table->string('event_name', 191)->default('');
            $table->text('payload')->nullable();
            $table->text('old_value')->nullable();
            $table->text('value')->nullable();
            $table->string('source', 191)->default('');
            $table->dateTime('created_at');
            $table->index('created_at');
            $table->index('type');
            $table->index(['object_title', 'property_title']);
        });
    }

    if (!$schema->hasTable('websocket_tickets')) {
        $schema->create('websocket_tickets', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->char('ticket_hash', 64)->unique();
            $table->char('stamp', 64);
            $table->text('channels');
            $table->unsignedBigInteger('since_id')->default(0);
            $table->dateTime('created_at');
            $table->dateTime('expires_at');
            $table->dateTime('revoked_at')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->index(['user_id', 'revoked_at']);
            $table->index('expires_at');
            $table->index('created_at');
        });
    }
};
