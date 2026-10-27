<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * Подписки модулей на свойства объектов.
 *
 * 2026-10-27 — таблица создана вместо alpha-колонки `pvalues.LINKED_MODULES`:
 *              подписка хранится отдельной строкой, её выбирает пользователь
 *              в интерфейсе модуля, а модуль только принимает изменения.
 */
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();

    if ($schema->hasTable('property_subscriptions')) {
        return;
    }

    $schema->create('property_subscriptions', static function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('object_id');
        $table->integer('property_id');
        $table->string('module', 64);
        $table->timestamps();
        $table->unique(['object_id', 'property_id', 'module']);
        $table->index('module');
    });
};
