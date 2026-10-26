<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="settings" /> core · settings</div>
            <h1 class="md-ui-hero__title">Настройки</h1>
            <p class="md-ui-hero__lede">Legacy-таблица `settings` через SettingsRepository.</p>
        </div>
    </section>

    <x-md.card title="Поиск">
        <form method="get" action="/module/settings" class="md-form md-form--inline">
            <x-md.input name="q" label="Название или код" :value="$search" placeholder="SITE_TITLE" />
            <x-md.button type="submit" variant="secondary" icon="search">Найти</x-md.button>
        </form>
    </x-md.card>

    <x-md.card title="Параметры" :subtitle="'Всего: ' . count($settings)">
        @if ($settings === [])
            <x-md.empty-state title="Настроек нет" text="Поиск ничего не нашёл." icon="settings" />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead><tr><th>Код</th><th>Название</th><th>Значение</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($settings as $setting)
                        <tr>
                            <td><code>{{ $setting['name'] }}</code></td>
                            <td>{{ $setting['title'] }}</td>
                            <td class="md-table__muted">{{ \Illuminate\Support\Str::limit($setting['value'], 80) }}</td>
                            <td><x-md.button href="/module/settings/{{ $setting['id'] }}/edit" variant="secondary" size="sm" icon="edit">Изменить</x-md.button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-md.card>
</div>
