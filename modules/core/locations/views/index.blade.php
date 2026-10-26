<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="map-pin" /> core · locations</div>
            <h1 class="md-ui-hero__title">Локации</h1>
            <p class="md-ui-hero__lede">Новая таблица `locations`, без legacy-полей.</p>
        </div>
        <x-md.button href="/module/locations/create" icon="plus">Добавить</x-md.button>
    </section>

    <x-md.card title="Список" :subtitle="'Всего: ' . count($locations)">
        @if ($locations === [])
            <x-md.empty-state title="Локаций нет" text="Добавьте первую локацию." icon="map-pin" />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead><tr><th>Название</th><th>Приоритет</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($locations as $location)
                        <tr>
                            <td>{{ $location['title'] }}</td>
                            <td>{{ $location['priority'] }}</td>
                            <td>
                                <x-md.button href="/module/locations/{{ $location['id'] }}/edit" variant="secondary" size="sm" icon="edit">Изменить</x-md.button>
                                <form method="post" action="/module/locations/{{ $location['id'] }}/delete" class="md-inline-form">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.button type="submit" variant="danger" size="sm" icon="trash">Удалить</x-md.button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-md.card>
</div>
