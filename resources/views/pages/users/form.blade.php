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
        :lede="$isNew ? 'В классе Users автоматически появится объект с именем логина.' : 'Учётная запись, роли и связанный объект пользователя.'"
        :meta="$isNew ? [] : [
            ['icon' => 'user', 'label' => 'Логин', 'value' => $user['username']],
            ['icon' => 'shield', 'label' => 'Доступ', 'value' => $user['is_admin'] ? 'администратор' : 'пользователь'],
        ]">
        <x-slot:actions>
            <x-md.button href="/users" variant="secondary" icon="arrow-left">К пользователям</x-md.button>
            @if (!$isNew)
                <form method="post" action="/users/{{ $user['id'] }}/delete" class="md-inline-form"
                      data-md-confirm="Удалить пользователя «{{ $user['username'] }}», его объект и историю значений?">
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
                                minlength="8" maxlength="64" pattern="[A-Za-z_][A-Za-z0-9_]{7,63}" autocomplete="username"
                                hint="8–64 символа: латиница, цифры и _. Первый — буква или _. Смена логина переименует связанный объект." />
                    <x-md.input name="name" label="Имя" :value="$field('name')" required
                                hint="Отображается в панели и журнале действий." />
                    <x-md.input name="password" type="password" label="Пароль" :value="''"
                                :required="$isNew"
                                autocomplete="new-password" minlength="8"
                                :hint="$isNew ? 'Минимум 8 символов, включая специальный символ, например ! или _.' : 'Оставьте пустым, чтобы сохранить пароль. Новый — от 8 символов со спецсимволом.'" />
                </div>
                <x-md.checkbox name="is_admin" value="1" label="Администратор"
                               description="Полный доступ к панели, включая пользователей, роли и обслуживание."
                               :checked="$isAdmin" />
            </div>
        </x-md.card>

        <x-md.card title="Роли и разрешения" subtitle="Права всех выбранных ролей складываются. Администратор имеет полный доступ независимо от ролей.">
            @if ($roles === [])
                <x-md.empty-state icon="shield" title="Ролей нет"
                                  text="Вернитесь к списку пользователей и откройте «Роли и права»." />
            @else
                <div class="md-ui-form-stack">
                    <div class="md-ui-form-grid">
                        @foreach ($roles as $role)
                            <div class="md-ui-form-stack"><x-md.checkbox
                                name="role_ids[]"
                                :value="(string)$role['id']"
                                :label="$role['title']"
                                :description="$role['name']"
                                :checked="in_array($role['id'], $selectedRoles, true)" />
                                <details>
                                    <summary class="md-table__muted">Разрешения роли</summary>
                                    <ul>
                                        @foreach ($permissions as $permission)
                                            @if (in_array($permission['id'], $grants[$role['id']] ?? [], true))
                                                <li>{{ $permission['title'] }} <code>{{ $permission['name'] }}</code></li>
                                            @endif
                                        @endforeach
                                        @if (($grants[$role['id']] ?? []) === [])<li>Нет разрешений</li>@endif
                                    </ul>
                                </details>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-md.card>

        @if ($object !== null)
            <x-md.card title="Объект пользователя" :subtitle="'Users → ' . $object['title']">
                <div class="md-ui-form-stack">
                    <p>Общие свойства: <code>isHome</code> — присутствие дома, <code>isWifiConnected</code> — подключение к Wi-Fi.</p>
                    <p>Методы <code>arrivedHome</code> и <code>leftHome</code> меняют присутствие. Wi-Fi обновляется интеграцией отдельно.</p>
                    <div class="md-ui-actions">
                        <x-md.button :href="'/objects/' . $object['id'] . '/edit?tab=properties'" variant="secondary" icon="box">Свойства и история</x-md.button>
                        <x-md.button :href="'/objects/' . $object['id'] . '/edit?tab=methods'" variant="ghost" icon="code">Методы</x-md.button>
                    </div>
                </div>
            </x-md.card>
        @endif

        <div class="md-ui-form-actions">
            <x-md.button type="submit" icon="check">{{ $isNew ? 'Создать пользователя' : 'Сохранить' }}</x-md.button>
            <x-md.button href="/users" variant="ghost">Отмена</x-md.button>
        </div>
    </form>
</div>
