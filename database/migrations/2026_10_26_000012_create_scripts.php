<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();

    if (!$schema->hasTable('script_categories')) {
        $schema->create('script_categories', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title', 191);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    if (!$schema->hasTable('scripts')) {
        $schema->create('scripts', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title', 191);
            $table->text('code')->nullable();
            $table->text('description')->nullable();
            $table->integer('category_id')->default(0);
            $table->boolean('run_periodically')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index('category_id');
        });
    }
};
