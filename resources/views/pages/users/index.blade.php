<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="users" /> core · auth</div>
            <h1 class="md-ui-hero__title">Пользователи</h1>
            <p class="md-ui-hero__lede">Новая таблица `users`: пароли через password_hash, удаление мягкое.</p>
        </div>
        <x-md.button href="/module/users/create" icon="plus">Добавить</x-md.button>
    </section>

    <x-md.card title="Поиск">
        <form method="get" action="/module/users" class="md-form md-form--inline">
            <x-md.input name="q" label="Логин или имя" :value="$search" />
            <x-md.button type="submit" variant="secondary" icon="search">Найти</x-md.button>
        </form>
    </x-md.card>

    <x-md.card title="Список" :subtitle="'Всего: ' . count($users)">
        @if ($users === [])
            <x-md.empty-state title="Пользователей нет" text="Добавьте первого пользователя." icon="users" />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead><tr><th>Логин</th><th>Имя</th><th>Админ</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td><code>{{ $user['username'] }}</code></td>
                            <td>{{ $user['name'] }}</td>
                            <td><x-md.badge :tone="$user['is_admin'] ? 'success' : 'neutral'">{{ $user['is_admin'] ? 'да' : 'нет' }}</x-md.badge></td>
                            <td>
                                <x-md.button href="/module/users/{{ $user['id'] }}/edit" variant="secondary" size="sm" icon="edit">Изменить</x-md.button>
                                <form method="post" action="/module/users/{{ $user['id'] }}/delete" class="md-inline-form">
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
