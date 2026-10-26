@php
    $value = static fn (string $key): string => (string)($draft[$key] ?? $property[$key] ?? '');
    $classId = (int)($draft['class_id'] ?? $property['class_id'] ?? 0);
    $keep = (bool)($draft['keep_history'] ?? $property['keep_history'] ?? false);
@endphp
<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="list" /> {{ $property === null ? 'новое' : 'редактирование' }}</div>
            <h1 class="md-ui-hero__title">{{ $property === null ? 'Новое свойство' : $property['title'] }}</h1>
        </div>
    </section>

    @if ($error !== null)
        <x-md.alert type="danger" :text="$error" />
    @endif

    <x-md.card title="Данные">
        <form method="post" action="{{ $property === null ? '/module/properties/create' : '/module/properties/' . $property['id'] . '/edit' }}" class="md-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">
            <x-md.select name="class_id" label="Класс">
                <option value="0">Без класса</option>
                @foreach ($classes as $class)
                    <option value="{{ $class['id'] }}" @selected($classId === $class['id'])>{{ $class['title'] }}</option>
                @endforeach
            </x-md.select>
            <x-md.input name="title" label="Название" :value="$value('title')" required />
            <x-md.textarea name="description" label="Описание" :value="$value('description')" rows="3" />
            <x-md.select name="data_type" label="Тип данных">
                @foreach (['string' => 'Строка', 'int' => 'Число', 'float' => 'Дробное', 'bool' => 'Да/Нет'] as $type => $label)
                    <option value="{{ $type }}" @selected($value('data_type') === $type)>{{ $label }}</option>
                @endforeach
            </x-md.select>
            <x-md.checkbox name="keep_history" value="1" label="Хранить историю" :checked="$keep" />
            <x-md.input name="on_change" label="OnChange" :value="$value('on_change')" />
            <div class="md-ui-actions">
                <x-md.button type="submit" icon="check">Сохранить</x-md.button>
                <x-md.button href="/module/properties" variant="ghost">Отмена</x-md.button>
            </div>
        </form>
    </x-md.card>
</div>
