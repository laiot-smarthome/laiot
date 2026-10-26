<?php

declare(strict_types=1);

namespace MajorDoMo\Database;

use Illuminate\Database\Eloquent\Model;

/**
 * Тестовая модель среза. В боевом коде таких моделей не будет: это проверка
 * Eloquent на SQLite до подключения к MariaDB.
 *
 * @property int $id
 * @property string $message
 */
final class SpikeNote extends Model
{
    protected $table = 'spike_notes';

    protected $primaryKey = 'id';

    public $timestamps = false;

    /** @var list<string> */
    protected $guarded = [];
}
