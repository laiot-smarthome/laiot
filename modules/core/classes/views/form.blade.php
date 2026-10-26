<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="box" /> {{ $class === null ? 'новый' : 'редактирование' }}</div>
            <h1 class="md-ui-hero__title">{{ $class === null ? 'Новый класс' : $class['title'] }}</h1>
        </div>
    </section>

    @if ($error !== null)
        <x-md.alert type="danger" :text="$error" />
    @endif

    <x-md.card title="Данные">
        <form method="post" action="{{ $class === null ? '/module/classes/create' : '/module/classes/' . $class['id'] . '/edit' }}" class="md-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">
            <x-md.input name="title" label="Название" :value="$title" required />
            <div class="md-ui-actions">
                <x-md.button type="submit" icon="check">Сохранить</x-md.button>
                <x-md.button href="/module/classes" variant="ghost">Отмена</x-md.button>
            </div>
        </form>
    </x-md.card>
</div>
