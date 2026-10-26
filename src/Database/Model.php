<?php

declare(strict_types=1);

namespace MajorDoMo\Database;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Базовая модель MyDoMo.
 *
 * Старые таблицы MajorDoMo используют `ID` и не имеют `created_at/updated_at`.
 * Новые модели наследуются отсюда, чтобы не повторять эти настройки.
 */
abstract class Model extends EloquentModel
{
    protected $primaryKey = 'ID';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    /** @var list<string> */
    protected $guarded = [];
}
