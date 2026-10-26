<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

return static function (Connection $connection): void {
    $schema = $connection->getSchemaBuilder();
    if ($schema->hasTable('methods')) {
        return;
    }

    $schema->create('methods', static function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('class_id')->default(0);
        $table->string('title', 191);
        $table->text('description')->nullable();
        $table->text('code')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->index(['class_id', 'title']);
    });
};
