<?php

declare(strict_types=1);

namespace MajorDoMo\Zigbee;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use MajorDoMo\Modules\ModuleDb;
use Throwable;

/**
 * Таблицы Zigbee-модуля.
 *
 * Core-модуль не проходит `module:install`, поэтому схема создаётся при первом
 * обращении. Проверка выполняется один раз на процесс: страница устройства и
 * поток сообщений не должны ходить в information_schema на каждый вызов.
 *
 * В отличие от alpha, таблицы получают префикс SDK (`module_zigbee_*`);
 * старые данные `zigbeedev`/`zigbee` не читаются и переносятся отдельной
 * операцией импорта.
 *
 * 2026-10-28 — схема core-модуля вместо alpha-таблиц `zigbee_*`.
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

        if (!$this->moduleDb->hasTable('bridges')) {
            $this->create('bridges', static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title', 190);
                $table->string('base_topic', 190)->unique();
                $table->boolean('enabled')->default(true);
                $table->string('state', 30)->default('unknown');
                $table->dateTime('last_seen')->nullable();
                $table->text('info')->nullable();
            });
        }

        if (!$this->moduleDb->hasTable('devices')) {
            $this->create('devices', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedBigInteger('bridge_id')->index();
                $table->string('ieee_address', 64)->nullable();
                $table->string('friendly_name', 190);
                $table->string('title', 190)->default('');
                $table->string('model', 190)->default('');
                $table->string('vendor', 190)->default('');
                $table->text('description')->nullable();
                $table->string('available', 30)->default('unknown');
                $table->string('status', 20)->default('discovered');
                $table->unsignedBigInteger('location_id')->default(0);
                $table->dateTime('last_seen')->nullable();
                $table->text('metadata')->nullable();
                $table->text('health_config')->nullable();
                $table->unique(['bridge_id', 'ieee_address']);
                $table->unique(['bridge_id', 'friendly_name']);
                $table->index(['status', 'available']);
            });
        }

        if (!$this->moduleDb->hasTable('properties')) {
            $this->create('properties', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedBigInteger('device_id')->index();
                $table->string('path', 190);
                $table->string('label', 190)->default('');
                $table->text('expose')->nullable();
                $table->text('raw_value')->nullable();
                $table->text('value')->nullable();
                $table->dateTime('updated')->nullable();
                $table->unique(['device_id', 'path']);
            });
        }

        if (!$this->moduleDb->hasTable('bindings')) {
            $this->create('bindings', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedBigInteger('property_id')->unique();
                $table->string('linked_object', 190)->default('');
                $table->string('linked_property', 190)->default('');
                $table->string('linked_method', 190)->default('');
                $table->unsignedBigInteger('linked_object_id')->nullable();
                $table->unsignedBigInteger('linked_property_id')->nullable();
                $table->boolean('enabled')->default(true);
                $table->boolean('write_enabled')->default(false);
                $table->string('event_mode', 20)->default('always');
                $table->text('incoming')->nullable();
                $table->text('outgoing')->nullable();
                $table->text('last_delivered')->nullable();
                $table->text('last_error')->nullable();
                $table->index(
                    ['linked_object', 'linked_property', 'enabled', 'write_enabled'],
                    'module_zigbee_bindings_target'
                );
            });
        }

        if (!$this->moduleDb->hasTable('events')) {
            $this->create('events', static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedBigInteger('bridge_id')->index();
                $table->unsignedBigInteger('device_id')->nullable();
                $table->dateTime('added')->index();
                $table->string('level', 20)->default('info');
                $table->string('topic', 512)->default('');
                $table->text('message');
            });
        }

        if (!$this->moduleDb->hasTable('health_settings')) {
            $this->create('health_settings', static function (Blueprint $table): void {
                $table->unsignedInteger('id')->primary();
                $table->text('config');
            });
        }

        if (!$this->moduleDb->hasTable('health_state')) {
            $this->create('health_state', static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('subject', 100)->unique();
                $table->text('data');
            });
        }

        $this->ready = true;
    }

    /**
     * CREATE TABLE может проиграть гонку второму процессу на свежей установке:
     * это не ошибка, если таблица уже появилась.
     *
     * @param Closure(Blueprint): void $definition
     */
    private function create(string $table, Closure $definition): void
    {
        try {
            $this->moduleDb->createTable($table, $definition);
        } catch (Throwable $error) {
            if (!$this->moduleDb->hasTable($table)) {
                throw $error;
            }
        }
    }
}
