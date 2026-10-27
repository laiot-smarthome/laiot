<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Система"
        icon="users"
        title="Пользователи и роли"
        lede="Учётные записи, роли и объекты пользователей дома. Вход в админку доступен только администраторам."
        :meta="[
            ['icon' => 'users', 'label' => 'Пользователей', 'value' => count($users)],
        ]">
        <x-slot:actions>
            @yoyo('user-roles')
            <x-md.button href="/users/create" icon="plus">Новый пользователь</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    <x-md.table label="Пользователи">
        <x-slot:toolbar>
            <form class="md-ui-search" method="get" action="/users" role="search">
                <input class="md-input" type="search" name="q" value="{{ $search }}"
                       placeholder="Поиск по логину и имени" aria-label="Поиск пользователей" autocomplete="off">
                <button class="md-btn md-btn--secondary" type="submit">
                    <i class="ti ti-search" aria-hidden="true"></i><span class="md-btn__label">Найти</span>
                </button>
                @if ($search !== '')
                    <a class="md-btn md-btn--ghost" href="/users">Сбросить</a>
                @endif
            </form>
            <div class="md-table__meta">Найдено: {{ count($users) }}</div>
        </x-slot:toolbar>

        @if ($users === [])
            <x-slot:empty>
                <div class="md-empty">
                    <span class="md-empty__icon"><i class="ti ti-users" aria-hidden="true"></i></span>
                    <div class="md-empty__title">{{ $search !== '' ? 'Пользователи не найдены' : 'Пользователей нет' }}</div>
                    <p class="md-empty__text">{{ $search !== '' ? 'Измените запрос или сбросьте поиск.' : 'Создайте администратора — без него вход в панель невозможен.' }}</p>
                    <div class="md-empty__actions">
                        <a class="md-btn md-btn--primary" href="/users/create">
                            <i class="ti ti-plus" aria-hidden="true"></i><span class="md-btn__label">Новый пользователь</span>
                        </a>
                    </div>
                </div>
            </x-slot:empty>
        @else
            <x-slot:head>
                <th scope="col">Пользователь</th>
                <th scope="col">Логин</th>
                <th scope="col">Доступ</th>
                <th scope="col">Роли</th>
                <th scope="col">Дома / Wi-Fi</th>
                <th scope="col">Объект</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>

            <x-slot:body>
                @foreach ($users as $user)
                    <tr>
                        <td>
                            <div class="md-table__name">
                                <span class="md-table__icon">{{ mb_strtoupper(mb_substr($user['name'] !== '' ? $user['name'] : $user['username'], 0, 1)) }}</span>
                                <div>
                                    <a href="/users/{{ $user['id'] }}/edit"><strong>{{ $user['name'] !== '' ? $user['name'] : $user['username'] }}</strong></a>
                                </div>
                            </div>
                        </td>
                        <td><code class="md-table__code">{{ $user['username'] }}</code></td>
                        <td>
                            @if ($user['is_admin'])
                                <x-md.badge tone="success">администратор</x-md.badge>
                            @else
                                <x-md.badge tone="neutral">пользователь</x-md.badge>
                            @endif
                        </td>
                        <td>
                            @php $userRoles = $rolesByUser[$user['id']] ?? []; @endphp
                            @if ($userRoles === [])
                                <span class="md-table__muted">Без ролей</span>
                            @else
                                <div class="md-ui-actions">
                                    @foreach ($userRoles as $role)
                                        <x-md.badge tone="outline">{{ $role }}</x-md.badge>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="md-ui-actions">
                                <x-md.badge :tone="($presence[$user['id']]['isHome'] ?? '') === '1' ? 'success' : 'neutral'">{{ ($presence[$user['id']]['isHome'] ?? '') === '1' ? 'Дома' : 'Вне дома' }}</x-md.badge>
                                <x-md.badge :tone="($presence[$user['id']]['isWifiConnected'] ?? '') === '1' ? 'info' : 'neutral'">{{ ($presence[$user['id']]['isWifiConnected'] ?? '') === '1' ? 'Wi-Fi подключён' : 'Без Wi-Fi' }}</x-md.badge>
                            </div>
                        </td>
                        <td><a href="/objects/{{ $user['object_id'] }}/edit"><code>{{ $user['username'] }}</code></a></td>
                        <td>
                            <div class="md-table__actions">
                                <x-md.icon-button href="/users/{{ $user['id'] }}/edit" icon="pencil" label="Открыть пользователя" size="sm" />
                                <form method="post" action="/users/{{ $user['id'] }}/delete" class="md-inline-form"
                                      data-md-confirm="Удалить пользователя «{{ $user['username'] }}», его объект и историю значений?">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.icon-button type="submit" icon="trash" label="Удалить пользователя" variant="danger" size="sm" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        @endif
    </x-md.table>
</div>
