<?php

declare(strict_types=1);

namespace MajorDoMo\Database;

/**
 * Системные настройки MyDoMo.
 *
 * Новая таблица `settings` с lowercase-колонками и timestamps; никаких
 * legacy-адаптеров.
 */
final class SettingsRepository extends Repository
{
    public function get(string $name, ?string $default = null): ?string
    {
        $value = Setting::query()->where('name', $name)->value('value');

        if ($value === null) {
            return $default;
        }

        return (string)$value;
    }

    /**
     * @return list<array{id:int,name:string,title:string,value:string,type:string}>
     */
    public function details(string $search = ''): array
    {
        $query = Setting::query();
        if ($search !== '') {
            $query->where(static function ($query) use ($search): void {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('title', 'like', '%' . $search . '%');
            });
        }

        return $query->orderBy('name')
            ->get()
            ->map(static fn (object $setting): array => [
                'id' => (int)$setting->id,
                'name' => (string)$setting->name,
                'title' => (string)$setting->title,
                'value' => (string)($setting->value ?? ''),
                'type' => (string)$setting->type,
            ])
            ->all();
    }

    /**
     * @return array{id:int,name:string,title:string,value:string,type:string}|null
     */
    public function find(int $id): ?array
    {
        $setting = Setting::query()->find($id);
        if ($setting === null) {
            return null;
        }

        return [
            'id' => (int)$setting->id,
            'name' => (string)$setting->name,
            'title' => (string)$setting->title,
            'value' => (string)($setting->value ?? ''),
            'type' => (string)$setting->type,
        ];
    }

    public function save(int $id, string $value): void
    {
        Setting::query()->where('id', $id)->update(['value' => $value]);
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        /** @var array<string, string|null> $values */
        $values = Setting::query()
            ->orderBy('name')
            ->pluck('value', 'name')
            ->all();

        return $values;
    }
}
