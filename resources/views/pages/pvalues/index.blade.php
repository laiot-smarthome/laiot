<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="list" /> core · values</div>
            <h1 class="md-ui-hero__title">Значения свойств</h1>
            <p class="md-ui-hero__lede">Таблица `property_values`: объект → свойство → значение.</p>
        </div>
    </section>

    <x-md.card title="Поиск">
        <form method="get" action="/module/pvalues" class="md-form md-form--inline">
            <x-md.input name="q" label="Объект, свойство или значение" :value="$search" />
            <x-md.button type="submit" variant="secondary" icon="search">Найти</x-md.button>
        </form>
    </x-md.card>

    <x-md.card title="Значения" :subtitle="'Всего: ' . count($values)">
        @if ($values === [])
            <x-md.empty-state title="Значений нет" text="Значения появляются на странице объекта." icon="list" />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead><tr><th>Объект</th><th>Свойство</th><th>Значение</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($values as $value)
                        <tr>
                            <td>{{ $value['object_title'] }}</td>
                            <td><code>{{ $value['property_title'] }}</code></td>
                            <td>
                                <form method="post" action="/module/pvalues/{{ $value['id'] }}" class="md-inline-form">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <input class="md-input" type="text" name="value" value="{{ $value['value'] }}">
                                    <x-md.button type="submit" variant="secondary" size="sm" icon="check">OK</x-md.button>
                                </form>
                            </td>
                            <td>
                                <form method="post" action="/module/pvalues/{{ $value['id'] }}/delete">
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
