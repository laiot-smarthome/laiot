<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="settings" /> {{ $setting['name'] }}</div>
            <h1 class="md-ui-hero__title">{{ $setting['title'] }}</h1>
            <p class="md-ui-hero__lede">Значение сохраняется в legacy `settings` через репозиторий.</p>
        </div>
    </section>

    <x-md.card title="Значение">
        <form method="post" action="/module/settings/{{ $setting['id'] }}" class="md-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">
            <x-md.textarea name="value" label="Значение" :value="$setting['value']" rows="6" />
            <div class="md-ui-actions">
                <x-md.button type="submit" icon="check">Сохранить</x-md.button>
                <x-md.button href="/module/settings" variant="ghost">Отмена</x-md.button>
            </div>
        </form>
    </x-md.card>
</div>
