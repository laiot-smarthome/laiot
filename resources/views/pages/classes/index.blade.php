<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="box" /> core · classes</div>
            <h1 class="md-ui-hero__title">Классы</h1>
            <p class="md-ui-hero__lede">Новая таблица `classes`. Свойства и методы — следующий слой.</p>
        </div>
        <x-md.button href="/module/classes/create" icon="plus">Добавить</x-md.button>
    </section>

    <x-md.card title="Список" :subtitle="'Всего: ' . count($classes)">
        @if ($classes === [])
            <x-md.empty-state title="Классов нет" text="Добавьте первый класс." icon="box" />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead><tr><th>Название</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($classes as $class)
                        <tr>
                            <td>{{ $class['title'] }}</td>
                            <td>
                                <x-md.button href="/module/classes/{{ $class['id'] }}/edit" variant="secondary" size="sm" icon="edit">Изменить</x-md.button>
                                <form method="post" action="/module/classes/{{ $class['id'] }}/delete" class="md-inline-form">
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
