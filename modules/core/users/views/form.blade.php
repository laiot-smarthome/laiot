@php
    $value = static fn (string $key): string => (string)($draft[$key] ?? $user[$key] ?? '');
    $isAdmin = (bool)($draft['is_admin'] ?? $user['is_admin'] ?? false);
@endphp
<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="user" /> {{ $user === null ? 'новый' : 'редактирование' }}</div>
            <h1 class="md-ui-hero__title">{{ $user === null ? 'Новый пользователь' : $user['username'] }}</h1>
        </div>
    </section>

    @if ($error !== null)
        <x-md.alert type="danger" :text="$error" />
    @endif

    <x-md.card title="Данные">
        <form method="post" action="{{ $user === null ? '/module/users/create' : '/module/users/' . $user['id'] . '/edit' }}" class="md-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">
            <x-md.input name="username" label="Логин" :value="$value('username')" required />
            <x-md.input name="name" label="Имя" :value="$value('name')" required />
            <x-md.input name="password" type="password" label="Пароль" :required="$user === null" hint="{{ $user === null ? 'Минимум 8 символов' : 'Оставьте пустым, чтобы не менять' }}" />
            <x-md.checkbox name="is_admin" value="1" label="Администратор" :checked="$isAdmin" />
            @if ($roles !== [])
                <fieldset class="md-field">
                    <legend class="md-field__label">Роли</legend>
                    @foreach ($roles as $role)
                        <x-md.checkbox :name="'role_ids[]'" :value="(string)$role['id']" :label="$role['title']" :checked="in_array($role['id'], $selectedRoles, true)" />
                    @endforeach
                </fieldset>
            @endif
            <div class="md-ui-actions">
                <x-md.button type="submit" icon="check">Сохранить</x-md.button>
                <x-md.button href="/module/users" variant="ghost">Отмена</x-md.button>
            </div>
        </form>
    </x-md.card>
</div>
