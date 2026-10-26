@php
    $value = static fn (string $key): string => (string)($draft[$key] ?? $method[$key] ?? '');
    $classId = (int)($draft['class_id'] ?? $method['class_id'] ?? 0);
@endphp
<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="terminal" /> {{ $method === null ? 'новый' : 'редактирование' }}</div>
            <h1 class="md-ui-hero__title">{{ $method === null ? 'Новый метод' : $method['title'] }}</h1>
        </div>
    </section>

    @if ($error !== null)
        <x-md.alert type="danger" :text="$error" />
    @endif

    <x-md.card title="Данные">
        <form method="post" action="{{ $method === null ? '/module/methods/create' : '/module/methods/' . $method['id'] . '/edit' }}" class="md-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">
            <x-md.select name="class_id" label="Класс">
                <option value="0">Без класса</option>
                @foreach ($classes as $class)
                    <option value="{{ $class['id'] }}" @selected($classId === $class['id'])>{{ $class['title'] }}</option>
                @endforeach
            </x-md.select>
            <x-md.input name="title" label="Название" :value="$value('title')" required />
            <x-md.textarea name="description" label="Описание" :value="$value('description')" rows="3" />
            <x-md.code name="code" label="PHP-код" :value="$value('code')" rows="16" hint="Без тегов PHP; выполняется через CodeRunner." />
            <div class="md-ui-actions">
                <x-md.button type="submit" icon="check">Сохранить</x-md.button>
                <x-md.button href="/module/methods" variant="ghost">Отмена</x-md.button>
            </div>
        </form>
    </x-md.card>
</div>
