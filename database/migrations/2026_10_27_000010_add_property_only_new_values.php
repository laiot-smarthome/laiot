<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * Политика повторной записи свойства.
 *
 * 2026-10-27 — по умолчанию повторное значение запускает OnChange и доставку
 * модулям, как раньше. Если `only_new_values` включён, запись того же значения
 * принимается, но цепочка не запускается; ответственность за фильтрацию
 * повторов больше не лежит на каждом модуле и пользовательском коде.
 */
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();

    if ($schema->hasTable('properties') && !$schema->hasColumn('properties', 'only_new_values')) {
        $schema->table('properties', static function (Blueprint $table): void {
            $table->boolean('only_new_values')->default(false);
        });
    }
};
