<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="history" /> core · history</div>
            <h1 class="md-ui-hero__title">История</h1>
            <p class="md-ui-hero__lede">История значений свойств из `property_history`.</p>
        </div>
    </section>

    <x-md.card title="Поиск">
        <form method="get" action="/module/history" class="md-form md-form--inline">
            <x-md.input name="q" label="Объект, свойство или значение" :value="$search" />
            <x-md.button type="submit" variant="secondary" icon="search">Найти</x-md.button>
        </form>
    </x-md.card>

    <x-md.card title="Записи" :subtitle="'Всего: ' . count($rows)">
        @if ($rows === [])
            <x-md.empty-state title="Истории нет" text="Значения ещё не менялись." icon="history" />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead><tr><th>Дата</th><th>Объект</th><th>Свойство</th><th>Значение</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="md-table__muted">{{ $row['created_at'] }}</td>
                            <td>{{ $row['object_title'] }}</td>
                            <td><code>{{ $row['property_title'] }}</code></td>
                            <td>{{ $row['value'] }}</td>
                            <td>
                                <form method="post" action="/module/history/{{ $row['id'] }}/delete">
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
