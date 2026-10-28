<?php

declare(strict_types=1);

namespace MajorDoMo\Mqtt;

use Illuminate\Database\Schema\Blueprint;
use MajorDoMo\Modules\ModuleDb;

/**
 * Таблицы MQTT-модуля.
 *
 * Core-модуль не проходит `module:install`, поэтому его схема создаётся при
 * первом обращении. Проверка выполняется один раз на процесс: лишний
 * information_schema-запрос на каждое сообщение или страницу не нужен.
 *
 * 2026-10-27 — схема core-модуля MQTT вместо таблиц alpha `mqtt`/`mqtt_history`.
 */
final class Schema
{
    private bool $ready = false;

    public function __construct(private readonly ModuleDb $moduleDb)
    {
    }

    public function ensure(): void
    {
        if ($this->ready) {
            return;
        }

        if (!$this->moduleDb->hasTable('topics')) {
            $this->create('topics', static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('path', 255);
                $table->string('path_key', 255)->index();
                $table->string('title', 255)->default('');
                $table->string('path_write', 255)->default('');
                $table->string('linked_object', 191)->default('');
                $table->string('linked_property', 191)->default('');
                $table->string('linked_method', 191)->default('');
                $table->unsignedBigInteger('linked_object_id')->nullable();
                $table->unsignedBigInteger('linked_property_id')->nullable();
                $table->unsignedBigInteger('location_id')->nullable();
                $table->boolean('logging')->default(false);
                $table->boolean('only_new_value')->default(false);
                $table->boolean('readonly')->default(false);
                $table->string('replace_list', 255)->default('');
                $table->unsignedTinyInteger('qos')->default(0);
                $table->boolean('retain')->default(false);
                $table->text('value')->nullable();
                $table->dateTime('updated')->nullable()->index();
                $table->timestamps();
                $table->unique('path');
                $table->index(['linked_object', 'linked_property']);
            });
        }

        if (!$this->moduleDb->hasTable('history')) {
            $this->create('history', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedBigInteger('topic_id')->index();
                $table->unsignedTinyInteger('direction')->default(0);
                $table->string('topic', 255)->default('');
                $table->text('value')->nullable();
                $table->text('payload')->nullable();
                $table->string('linked_data', 255)->default('');
                $table->dateTime('created_at')->index();
            });
        }

        if (!$this->moduleDb->hasTable('settings')) {
            $this->create('settings', static function (Blueprint $table): void {
                $table->string('name', 64)->primary();
                $table->text('value')->nullable();
            });
        }

        $this->ready = true;
    }

    /**
     * CREATE TABLE может проиграть гонку второму процессу на свежей установке:
     * это не ошибка, если таблица уже появилась.
     *
     * @param \Closure(Blueprint): void $definition
     */
    private function create(string $table, \Closure $definition): void
    {
        try {
            $this->moduleDb->createTable($table, $definition);
        } catch (\Throwable $error) {
            if (!$this->moduleDb->hasTable($table)) {
                throw $error;
            }
        }
    }
}
