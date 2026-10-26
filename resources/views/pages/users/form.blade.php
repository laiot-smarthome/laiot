@php
    $isNew = $user === null;
    $field = static fn (string $key): string => (string)($draft[$key] ?? $user[$key] ?? '');
    $isAdmin = (bool)($draft['is_admin'] ?? $user['is_admin'] ?? false);
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        :eyebrow="$isNew ? 'Новый пользователь' : 'Карточка пользователя'"
        icon="users"
        :title="$isNew ? 'Новый пользователь' : ($user['name'] !== '' ? $user['name'] : $user['username'])"
        :lede="$isNew ? 'Логин и пароль нужны для входа в панель; роли ограничивают доступ к разделам.' : 'Пароль хранится только в виде хеша и не отображается.'"
        :meta="$isNew ? [] : [
            ['icon' => 'user', 'label' => 'Логин', 'value' => $user['username']],
            ['icon' => 'shield', 'label' => 'Доступ', 'value' => $user['is_admin'] ? 'администратор' : 'пользователь'],
        ]">
        <x-slot:actions>
            <x-md.button href="/users" variant="secondary" icon="arrow-left">К пользователям</x-md.button>
            @if (!$isNew)
                <form method="post" action="/users/{{ $user['id'] }}/delete" class="md-inline-form"
                      data-md-confirm="Удалить пользователя «{{ $user['username'] }}»?">
                    <input type="hidden" name="_token" value="{{ $csrf }}">
                    <x-md.button type="submit" variant="danger" icon="trash">Удалить</x-md.button>
                </form>
            @endif
        </x-slot:actions>
    </x-md.page-hero>

    @if ($error !== null)
        <x-md.alert type="danger" title="Не удалось сохранить пользователя" :text="$error" />
    @endif

    <form method="post" action="{{ $isNew ? '/users/create' : '/users/' . $user['id'] . '/edit' }}" class="md-ui-form">
        <input type="hidden" name="_token" value="{{ $csrf }}">

        <x-md.card title="Учётная запись">
            <div class="md-ui-form-stack">
                <div class="md-ui-form-grid">
                    <x-md.input name="username" label="Логин" :value="$field('username')" required
                                hint="3–64 символа: латиница, цифры, точка, дефис, подчёркивание." />
                    <x-md.input name="name" label="Имя" :value="$field('name')" required
                                hint="Отображается в панели и журнале действий." />
                    <x-md.input name="password" type="password" label="Пароль" :value="''"
                                :required="$isNew"
                                :hint="$isNew ? 'Минимум 8 символов.' : 'Оставьте пустым, чтобы не менять пароль.'" />
                </div>
                <x-md.checkbox name="is_admin" value="1" label="Администратор"
                               description="Полный доступ к панели, включая пользователей, роли и обслуживание."
                               :checked="$isAdmin" />
            </div>
        </x-md.card>

        <x-md.card title="Роли" subtitle="Необязательно: роли уточняют права внутри панели">
            @if ($roles === [])
                <x-md.empty-state icon="shield" title="Ролей нет"
                                  text="Создайте роли в разделе «Роли», чтобы назначать их пользователям." />
            @else
                <div class="md-ui-form-stack">
                    <div class="md-ui-form-grid">
                        @foreach ($roles as $role)
                            <x-md.checkbox
                                name="role_ids[]"
                                :value="(string)$role['id']"
                                :label="$role['title']"
                                :description="$role['name']"
                                :checked="in_array($role['id'], $selectedRoles, true)" />
                        @endforeach
                    </div>
                </div>
            @endif
        </x-md.card>

        <div class="md-ui-form-actions">
            <x-md.button type="submit" icon="check">{{ $isNew ? 'Создать пользователя' : 'Сохранить' }}</x-md.button>
            <x-md.button href="/users" variant="ghost">Отмена</x-md.button>
        </div>
    </form>
</div>
