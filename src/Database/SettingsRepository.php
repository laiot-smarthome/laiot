<?php

declare(strict_types=1);

namespace MajorDoMo\Database;

/**
 * Чтение системных настроек через модель, а не через SQL в контроллере.
 *
 * Запись настроек появится отдельным сервисом: она затрагивает кэш,
 * типы значений и перезапуск циклов.
 */
final class SettingsRepository extends Repository
{
    public function get(string $name, ?string $default = null): ?string
    {
        $value = Setting::query()->where('NAME', $name)->value('VALUE');

        if ($value === null) {
            return $default;
        }

        return (string)$value;
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        /** @var array<string, string|null> $values */
        $values = Setting::query()
            ->orderBy('NAME')
            ->pluck('VALUE', 'NAME')
            ->all();

        return $values;
    }
}
