<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="history" /> core · audit</div>
            <h1 class="md-ui-hero__title">Журнал действий</h1>
            <p class="md-ui-hero__lede">Legacy-таблица `actions_log` через адаптер, без SQL в шаблоне.</p>
        </div>
    </section>

    <x-md.card title="Фильтр">
        <form method="get" action="/module/actions_log" class="md-form md-form--inline">
            <x-md.select name="type" label="Тип">
                <option value="">Все</option>
                @foreach ($types as $item)
                    <option value="{{ $item }}" @selected($type === $item)>{{ $item }}</option>
                @endforeach
            </x-md.select>
            <x-md.input name="q" label="Поиск" :value="$search" placeholder="Заголовок, пользователь, IP" />
            <x-md.button type="submit" variant="secondary" icon="search">Показать</x-md.button>
        </form>
    </x-md.card>

    <x-md.card title="Записи" :subtitle="'Найдено: ' . count($entries)">
        @if ($entries === [])
            <x-md.empty-state title="Записей нет" text="Журнал пуст или фильтр ничего не нашёл." icon="history" />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead><tr><th>ID</th><th>Дата</th><th>Тип</th><th>Событие</th><th>Пользователь</th><th>IP</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($entries as $entry)
                        <tr>
                            <td>{{ $entry['id'] }}</td>
                            <td class="md-table__muted">{{ $entry['added'] }}</td>
                            <td><code>{{ $entry['type'] }}</code></td>
                            <td>{{ $entry['title'] }}</td>
                            <td>{{ $entry['user'] }}</td>
                            <td>{{ $entry['ip'] }}</td>
                            <td>
                                <form method="post" action="/module/actions_log/{{ $entry['id'] }}/delete">
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
