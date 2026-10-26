<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="alert-triangle" /> core · errors</div>
            <h1 class="md-ui-hero__title">Ошибки</h1>
            <p class="md-ui-hero__lede">Ошибки ядра с request_id; запись не маскирует исходную ошибку.</p>
        </div>
    </section>

    <x-md.card title="Фильтр">
        <form method="get" action="/module/system_errors" class="md-form md-form--inline">
            <x-md.select name="level" label="Уровень">
                <option value="">Все</option>
                <option value="error" @selected($level === 'error')>error</option>
                <option value="warning" @selected($level === 'warning')>warning</option>
            </x-md.select>
            <x-md.button type="submit" variant="secondary" icon="search">Показать</x-md.button>
        </form>
    </x-md.card>

    <x-md.card title="Записи" :subtitle="'Найдено: ' . count($errors)">
        @if ($errors === [])
            <x-md.empty-state title="Ошибок нет" text="Чисто." icon="check" />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead><tr><th>ID</th><th>Дата</th><th>Уровень</th><th>Сообщение</th><th>Request ID</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($errors as $error)
                        <tr>
                            <td>{{ $error['id'] }}</td>
                            <td class="md-table__muted">{{ $error['created_at'] }}</td>
                            <td><x-md.badge :tone="$error['level'] === 'error' ? 'danger' : 'warning'">{{ $error['level'] }}</x-md.badge></td>
                            <td>{{ $error['message'] }}</td>
                            <td><code>{{ $error['request_id'] }}</code></td>
                            <td>
                                <form method="post" action="/module/system_errors/{{ $error['id'] }}/delete">
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
