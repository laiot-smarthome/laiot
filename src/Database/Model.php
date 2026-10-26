<?php

declare(strict_types=1);

namespace MajorDoMo\Database;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Базовая модель MyDoMo.
 *
 * Новая схема MyDoMo: `id` и обязательные `created_at/updated_at`.
 */
abstract class Model extends EloquentModel
{
    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    /** @var list<string> */
    protected $guarded = [];
}
