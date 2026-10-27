<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Аккаунт"
        icon="user-circle"
        title="Профиль"
        :lede="$user['username'] ?? ''"
        :meta="[
            ['icon' => 'shield', 'label' => 'Доступ', 'value' => ($user['is_admin'] ?? false) ? 'администратор' : 'пользователь'],
        ]">
        <x-slot:actions>
            @if ($user['is_admin'] ?? false)
                <x-md.button href="/users" variant="secondary" icon="users">Пользователи</x-md.button>
            @endif
            <form method="post" action="/logout" class="md-inline-form">
                <input type="hidden" name="_token" value="{{ $csrf }}">
                <x-md.button type="submit" variant="ghost" icon="logout">Выйти</x-md.button>
            </form>
        </x-slot:actions>
    </x-md.page-hero>

    @if ($error !== null)
        <x-md.alert type="danger" title="Не удалось сохранить профиль" :text="$error" />
    @endif

    <form method="post" action="/admin/profile" class="md-ui-form">
        <input type="hidden" name="_token" value="{{ $csrf }}">

        <x-md.card title="Личные данные">
            <div class="md-ui-form-stack">
                <div class="md-ui-form-grid">
                    <x-md.input name="name" label="Имя" :value="$name" required
                                hint="Отображается в панели и журнале действий." />
                    <x-md.input name="username" label="Логин" :value="$user['username'] ?? ''" disabled
                                hint="Логин меняет администратор в карточке пользователя." />
                    <x-md.input name="password" type="password" label="Новый пароль" value=""
                                autocomplete="new-password" minlength="8"
                                hint="Оставьте пустым, чтобы не менять. Новый пароль — от 8 символов со спецсимволом." />
                </div>
            </div>
        </x-md.card>

        <div class="md-ui-form-actions">
            <x-md.button type="submit" icon="check">Сохранить</x-md.button>
            <x-md.button href="/admin" variant="ghost">Отмена</x-md.button>
        </div>
    </form>
</div>
