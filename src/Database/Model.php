<?php

declare(strict_types=1);

namespace MajorDoMo\Database;

use Illuminate\Container\Container;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Базовая модель MyDoMo.
 *
 * Новая схема MyDoMo: `id` и обязательные `created_at/updated_at`.
 *
 * Изменения:
 * 2026-10-28 — штатные encrypted-casts используют шифратор текущего приложения.
 */
abstract class Model extends EloquentModel
{
    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    /** @var list<string> */
    protected $guarded = [];

    public static function currentEncrypter(): Encrypter
    {
        // Не записываем шифратор в глобальную static-переменную Eloquent:
        // отдельные Application в тестах не должны делиться ключом.
        return static::$encrypter ?? Container::getInstance()->make(Encrypter::class);
    }
}
