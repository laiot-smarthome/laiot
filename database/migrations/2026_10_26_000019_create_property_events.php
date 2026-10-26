<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * Журнал цепочки событий свойства: запись значения и вызов OnChange-обработчика.
 *
 * Строки создаются сервисом `PropertyValueService`, а не пользовательским кодом.
 * Таблица — только журнал: строки не редактируются, кроме финализации статуса
 * обработчика, который создаётся до запуска пользовательского метода.
 */
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('property_events')) {
        return;
    }

    $schema->create('property_events', static function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedBigInteger('parent_id')->default(0);
        $table->string('type', 32)->default('');
        $table->string('status', 32)->default('');
        $table->string('source', 191)->default('');
        $table->integer('object_id')->default(0);
        $table->integer('property_id')->default(0);
        $table->string('object_title', 191)->default('');
        $table->string('property_title', 191)->default('');
        $table->text('old_value')->nullable();
        $table->text('new_value')->nullable();
        $table->string('handler', 191)->default('');
        $table->text('message')->nullable();
        $table->text('chain')->nullable();
        $table->integer('depth')->default(0);
        $table->timestamps();
        $table->softDeletes();
        $table->index(['type', 'status']);
        $table->index(['object_id', 'property_id']);
        $table->index('created_at');
    });
};
