@php
    $value = static fn (string $key): string => (string)($draft[$key] ?? $script[$key] ?? '');
    $run = (bool)($draft['run_periodically'] ?? $script['run_periodically'] ?? false);
    $category = (int)($draft['category_id'] ?? $script['category_id'] ?? 0);
@endphp
<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="code" /> {{ $script === null ? 'новый' : 'редактирование' }}</div>
            <h1 class="md-ui-hero__title">{{ $script === null ? 'Новый скрипт' : $script['title'] }}</h1>
        </div>
    </section>

    @if ($error !== null)
        <x-md.alert type="danger" :text="$error" />
    @endif

    <x-md.card title="Код">
        <form method="post" action="{{ $script === null ? '/module/scripts/create' : '/module/scripts/' . $script['id'] . '/edit' }}" class="md-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">
            <x-md.input name="title" label="Название" :value="$value('title')" required />
            <x-md.textarea name="description" label="Описание" :value="$value('description')" rows="3" />
            <x-md.select name="category_id" label="Категория">
                <option value="0">Без категории</option>
                @foreach ($categories as $item)
                    <option value="{{ $item['id'] }}" @selected($category === $item['id'])>{{ $item['title'] }}</option>
                @endforeach
            </x-md.select>
            <x-md.checkbox name="run_periodically" value="1" label="Запускать периодически" :checked="$run" />
            <x-md.textarea name="code" label="PHP-код" :value="$value('code')" rows="18" />
            <div class="md-ui-actions">
                <x-md.button type="submit" icon="check">Сохранить</x-md.button>
                <x-md.button href="/module/scripts" variant="ghost">Отмена</x-md.button>
            </div>
        </form>
    </x-md.card>
</div>
