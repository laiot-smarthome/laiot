<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="list" /> core · properties</div>
            <h1 class="md-ui-hero__title">Свойства</h1>
            <p class="md-ui-hero__lede">Свойства классов на новой таблице `properties`.</p>
        </div>
        <x-md.button href="/module/properties/create" icon="plus">Добавить</x-md.button>
    </section>

    <x-md.card title="Список" :subtitle="'Всего: ' . count($properties)">
        @if ($properties === [])
            <x-md.empty-state title="Свойств нет" text="Добавьте свойство класса." icon="list" />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead><tr><th>Класс</th><th>Свойство</th><th>Тип</th><th>История</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($properties as $property)
                        <tr>
                            <td>{{ $classes[$property['class_id']] ?? '—' }}</td>
                            <td><code>{{ $property['title'] }}</code></td>
                            <td>{{ $property['data_type'] }}</td>
                            <td>{{ $property['keep_history'] ? 'да' : 'нет' }}</td>
                            <td>
                                <x-md.button href="/module/properties/{{ $property['id'] }}/edit" variant="secondary" size="sm" icon="edit">Изменить</x-md.button>
                                <form method="post" action="/module/properties/{{ $property['id'] }}/delete" class="md-inline-form">
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
