<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="map-pin" /> {{ $location === null ? 'новая' : 'редактирование' }}</div>
            <h1 class="md-ui-hero__title">{{ $location === null ? 'Новая локация' : $location['title'] }}</h1>
        </div>
    </section>

    @if ($error !== null)
        <x-md.alert type="danger" :text="$error" />
    @endif

    <x-md.card title="Данные">
        <form method="post" action="{{ $location === null ? '/module/locations/create' : '/module/locations/' . $location['id'] . '/edit' }}" class="md-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">
            <x-md.input name="title" label="Название" :value="$title" required />
            <x-md.input name="priority" type="number" label="Приоритет" :value="(string)$priority" />
            <div class="md-ui-actions">
                <x-md.button type="submit" icon="check">Сохранить</x-md.button>
                <x-md.button href="/module/locations" variant="ghost">Отмена</x-md.button>
            </div>
        </form>
    </x-md.card>
</div>
