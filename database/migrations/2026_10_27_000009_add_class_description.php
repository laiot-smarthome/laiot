<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * Описание класса для карточки и дерева.
 *
 * 2026-10-27 — в alpha у класса было отдельное описание; в новой схеме
 * остаётся `title` как системное имя, а человеческий текст хранится здесь.
 */
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();

    if ($schema->hasTable('classes') && !$schema->hasColumn('classes', 'description')) {
        $schema->table('classes', static function (Blueprint $table): void {
            $table->text('description')->nullable();
        });
    }
};
