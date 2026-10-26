<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="terminal" /> core · methods</div>
            <h1 class="md-ui-hero__title">Методы</h1>
            <p class="md-ui-hero__lede">Методы классов на новой таблице `methods`.</p>
        </div>
        <x-md.button href="/module/methods/create" icon="plus">Добавить</x-md.button>
    </section>

    <x-md.card title="Список" :subtitle="'Всего: ' . count($methods)">
        @if ($methods === [])
            <x-md.empty-state title="Методов нет" text="Добавьте метод класса." icon="terminal" />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead><tr><th>Класс</th><th>Метод</th><th>Описание</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($methods as $method)
                        <tr>
                            <td>{{ $classes[$method['class_id']] ?? '—' }}</td>
                            <td><code>{{ $method['title'] }}</code></td>
                            <td class="md-table__muted">{{ $method['description'] }}</td>
                            <td>
                                <x-md.button href="/module/methods/{{ $method['id'] }}/edit" variant="secondary" size="sm" icon="edit">Изменить</x-md.button>
                                <form method="post" action="/module/methods/{{ $method['id'] }}/delete" class="md-inline-form">
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
