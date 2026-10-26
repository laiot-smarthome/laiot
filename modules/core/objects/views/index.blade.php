<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="box" /> core · objects</div>
            <h1 class="md-ui-hero__title">Объекты</h1>
            <p class="md-ui-hero__lede">Класс, локация и значения свойств.</p>
        </div>
        <x-md.button href="/module/objects/create" icon="plus">Добавить</x-md.button>
    </section>

    <x-md.card title="Поиск">
        <form method="get" action="/module/objects" class="md-form md-form--inline">
            <x-md.input name="q" label="Название" :value="$search" />
            <x-md.button type="submit" variant="secondary" icon="search">Найти</x-md.button>
        </form>
    </x-md.card>

    <x-md.card title="Список" :subtitle="'Всего: ' . count($objects)">
        @if ($objects === [])
            <x-md.empty-state title="Объектов нет" text="Добавьте первый объект." icon="box" />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead><tr><th>Название</th><th>Класс</th><th>Локация</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($objects as $object)
                        <tr>
                            <td>{{ $object['title'] }}</td>
                            <td>{{ $classes[$object['class_id']] ?? '—' }}</td>
                            <td>{{ $locations[$object['location_id']] ?? '—' }}</td>
                            <td>
                                <x-md.button href="/module/objects/{{ $object['id'] }}/edit" variant="secondary" size="sm" icon="edit">Открыть</x-md.button>
                                <form method="post" action="/module/objects/{{ $object['id'] }}/delete" class="md-inline-form">
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
