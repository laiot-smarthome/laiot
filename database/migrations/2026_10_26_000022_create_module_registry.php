<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * Реестр установленных модулей LaIOT SDK.
 *
 * Core-модули не обязаны иметь строку реестра: они always enabled. Строка
 * нужна для сторонних модулей (modules/<id>), их версии и состояния.
 */
return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();

    if (!$schema->hasTable('module_registry')) {
        $schema->create('module_registry', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('module_id', 64)->unique();
            $table->string('type', 16)->default('module');
            $table->string('name', 190);
            $table->string('version', 32);
            $table->string('path', 255);
            $table->boolean('enabled')->default(true);
            $table->string('manifest_hash', 64);
            $table->text('settings')->nullable();
            $table->dateTime('installed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['enabled', 'module_id']);
        });
    }

    if (!$schema->hasTable('module_migrations')) {
        $schema->create('module_migrations', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('module_id', 64);
            $table->string('migration', 190);
            $table->dateTime('applied_at');
            $table->unique(['module_id', 'migration']);
        });
    }
};
