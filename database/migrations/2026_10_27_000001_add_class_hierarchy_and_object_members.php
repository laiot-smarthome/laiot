<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * Иерархия классов и члены объекта.
 *
 * Класс может наследовать свойства/методы родительского класса, а свойство
 * или метод может принадлежать конкретному объекту (`object_id`), а не
 * только классу. Значение по-прежнему хранится только в `property_values`
 * для объекта: у класса без объекта значения нет.
 */
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();

    if ($schema->hasTable('classes') && !$schema->hasColumn('classes', 'parent_id')) {
        $schema->table('classes', static function (Blueprint $table): void {
            $table->integer('parent_id')->default(0);
            $table->index(['parent_id', 'title'], 'classes_parent_title_index');
        });
    }

    if ($schema->hasTable('properties') && !$schema->hasColumn('properties', 'object_id')) {
        $schema->table('properties', static function (Blueprint $table): void {
            $table->integer('object_id')->default(0);
            $table->index(['object_id', 'title'], 'properties_object_title_index');
        });
    }

    if ($schema->hasTable('methods') && !$schema->hasColumn('methods', 'object_id')) {
        $schema->table('methods', static function (Blueprint $table): void {
            $table->integer('object_id')->default(0);
            $table->index(['object_id', 'title'], 'methods_object_title_index');
        });
    }
};
