<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * 2026-10-27 — локации получили иерархию дом → этаж → комната, ссылку на
 * автоматически созданный класс/объект комнаты и таймаут активности.
 * У свойства появилась роль в комнате: свет, обогрев, охлаждение или датчик.
 * Роль — единственная настройка, по которой система собирает активность;
 * отдельной таблицы привязок нет, чтобы не расходиться с самим свойством.
 */
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();

    if ($schema->hasTable('locations')) {
        $schema->table('locations', static function (Blueprint $table) use ($schema): void {
            if (!$schema->hasColumn('locations', 'parent_id')) {
                $table->integer('parent_id')->default(0);
                $table->index('parent_id');
            }
            if (!$schema->hasColumn('locations', 'kind')) {
                // Старые локации считаются помещениями: так они остаются
                // рабочими, пока владелец не назначит им этаж.
                $table->string('kind', 16)->default('room');
            }
            if (!$schema->hasColumn('locations', 'class_id')) {
                $table->integer('class_id')->default(0);
            }
            if (!$schema->hasColumn('locations', 'object_id')) {
                $table->integer('object_id')->default(0);
            }
            if (!$schema->hasColumn('locations', 'activity_timeout')) {
                $table->integer('activity_timeout')->default(900);
            }
        });
    }

    if ($schema->hasTable('properties') && !$schema->hasColumn('properties', 'room_role')) {
        $schema->table('properties', static function (Blueprint $table): void {
            $table->string('room_role', 24)->default('');
            $table->index('room_role');
        });
    }
};
