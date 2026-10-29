<?php

declare(strict_types=1);

namespace LaIOT\Models;

/**
 * Системная настройка LaIOT.
 *
 * @property int $id
 * @property string $name
 * @property string|null $value
 * @property string $title
 * @property string $type
 */
final class Setting extends Model
{
    protected $table = 'settings';
}
