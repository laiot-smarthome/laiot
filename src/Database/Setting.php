<?php

declare(strict_types=1);

namespace MajorDoMo\Database;

use Illuminate\Database\Eloquent\Model;

/**
 * Системная настройка из таблицы settings.
 *
 * @property int $ID
 * @property string $NAME
 * @property string|null $VALUE
 * @property string $TITLE
 * @property string $TYPE
 */
final class Setting extends Model
{
    protected $table = 'settings';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    /** @var list<string> */
    protected $guarded = [];
}
