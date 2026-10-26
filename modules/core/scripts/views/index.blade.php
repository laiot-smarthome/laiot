<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="code" /> core · scripts</div>
            <h1 class="md-ui-hero__title">Скрипты</h1>
            <p class="md-ui-hero__lede">Пользовательский PHP. Запуск циклов не затрагивается.</p>
        </div>
        <x-md.button href="/module/scripts/create" icon="plus">Добавить</x-md.button>
    </section>

    <x-md.card title="Поиск">
        <form method="get" action="/module/scripts" class="md-form md-form--inline">
            <x-md.input name="q" label="Название или описание" :value="$search" />
            <x-md.button type="submit" variant="secondary" icon="search">Найти</x-md.button>
        </form>
    </x-md.card>

    <x-md.card title="Список" :subtitle="'Всего: ' . count($scripts)">
        @if ($scripts === [])
            <x-md.empty-state title="Скриптов нет" text="Добавьте первый скрипт." icon="code" />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead><tr><th>Название</th><th>Описание</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($scripts as $script)
                        <tr>
                            <td><code>{{ $script['title'] }}</code></td>
                            <td class="md-table__muted">{{ $script['description'] }}</td>
                            <td>
                                <x-md.button href="/module/scripts/{{ $script['id'] }}/edit" variant="secondary" size="sm" icon="edit">Изменить</x-md.button>
                                <form method="post" action="/module/scripts/{{ $script['id'] }}/delete" class="md-inline-form">
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
