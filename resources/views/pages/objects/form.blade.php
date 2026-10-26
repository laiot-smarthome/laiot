@php
    $value = static fn (string $key): string => (string)($draft[$key] ?? $object[$key] ?? '');
    $classId = (int)($draft['class_id'] ?? $object['class_id'] ?? 0);
    $locationId = (int)($draft['location_id'] ?? $object['location_id'] ?? 0);
@endphp
<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="box" /> {{ $object === null ? 'новый' : 'объект' }}</div>
            <h1 class="md-ui-hero__title">{{ $object === null ? 'Новый объект' : $object['title'] }}</h1>
        </div>
    </section>

    @if ($error !== null)
        <x-md.alert type="danger" :text="$error" />
    @endif

    <x-md.card title="Объект">
        <form method="post" action="{{ $object === null ? '/module/objects/create' : '/module/objects/' . $object['id'] . '/edit' }}" class="md-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">
            <x-md.input name="title" label="Название" :value="$value('title')" required />
            <x-md.select name="class_id" label="Класс">
                <option value="0">Без класса</option>
                @foreach ($classes as $class)
                    <option value="{{ $class['id'] }}" @selected($classId === $class['id'])>{{ $class['title'] }}</option>
                @endforeach
            </x-md.select>
            <x-md.select name="location_id" label="Локация">
                <option value="0">Без локации</option>
                @foreach ($locations as $location)
                    <option value="{{ $location['id'] }}" @selected($locationId === $location['id'])>{{ $location['title'] }}</option>
                @endforeach
            </x-md.select>
            <x-md.textarea name="description" label="Описание" :value="$value('description')" rows="3" />

            @if ($properties !== [])
                <h3 class="md-card__title">Свойства</h3>
                @foreach ($properties as $property)
                    <x-md.input :name="'values[' . $property['id'] . ']'" :label="$property['title']" :value="$values[$property['id']] ?? ''" />
                @endforeach
            @endif

            <div class="md-ui-actions">
                <x-md.button type="submit" icon="check">Сохранить</x-md.button>
                <x-md.button href="/module/objects" variant="ghost">Отмена</x-md.button>
            </div>
        </form>
    </x-md.card>
</div>
