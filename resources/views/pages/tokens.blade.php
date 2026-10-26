<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Система"
        icon="key"
        title="API-токены"
        lede="Bearer или X-API-Key для внешних запросов. В базе хранится только SHA-256, сам токен показывается один раз."
        :meta="[
            ['icon' => 'key', 'label' => 'Токенов', 'value' => count($tokens)],
        ]">
        <x-slot:actions>
            <x-md.button href="/admin/api" variant="secondary" icon="book">Документация API</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    @if ($error !== null)
        <x-md.alert type="danger" title="Не удалось выпустить токен" :text="$error" />
    @endif
    @if ($issued !== null)
        <x-md.alert type="success" title="Токен выпущен — скопируйте сейчас"
                    :text="'Повторно он не показывается: ' . $issued" />
    @endif

    <x-md.card title="Выпустить токен" subtitle="Токен действует от имени выбранного пользователя и его ролей">
        <form method="post" action="/admin/tokens" class="md-ui-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">
            <div class="md-ui-form-grid">
                <x-md.select name="user_id" label="Пользователь" required>
                    @foreach ($users as $user)
                        <option value="{{ $user['id'] }}">{{ $user['name'] }} ({{ $user['username'] }})</option>
                    @endforeach
                </x-md.select>
                <x-md.input name="name" label="Имя токена" required placeholder="monitoring"
                            hint="Чтобы понимать, какому сервису он выдан." />
                <x-md.input name="days" type="number" label="Срок, дней" value="0" hint="0 — без срока; отзыв вручную." />
            </div>
            <div class="md-ui-form-actions">
                <x-md.button type="submit" icon="key">Выпустить токен</x-md.button>
            </div>
        </form>
    </x-md.card>

    <x-md.table label="Выпущенные токены">
        @if ($tokens === [])
            <x-slot:empty>
                <div class="md-empty">
                    <span class="md-empty__icon"><i class="ti ti-key" aria-hidden="true"></i></span>
                    <div class="md-empty__title">Токенов нет</div>
                    <p class="md-empty__text">Выпустите токен, чтобы внешний сервис мог обращаться к API.</p>
                </div>
            </x-slot:empty>
        @else
            <x-slot:head>
                <th scope="col">ID</th>
                <th scope="col">Имя</th>
                <th scope="col">Пользователь</th>
                <th scope="col">Создан</th>
                <th scope="col">Использован</th>
                <th scope="col">Статус</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>
            <x-slot:body>
                @foreach ($tokens as $token)
                    <tr>
                        <td><span class="md-table__muted">#{{ $token['id'] }}</span></td>
                        <td><code class="md-table__code">{{ $token['name'] }}</code></td>
                        <td>{{ $token['user_name'] }}</td>
                        <td><span class="md-table__muted">{{ $token['created_at'] }}</span></td>
                        <td><span class="md-table__muted">{{ $token['last_used_at'] !== '' ? $token['last_used_at'] : '—' }}</span></td>
                        <td>
                            @if ($token['revoked_at'] !== '')
                                <x-md.badge tone="danger">отозван</x-md.badge>
                            @else
                                <x-md.badge tone="success">активен</x-md.badge>
                            @endif
                        </td>
                        <td>
                            <div class="md-table__actions">
                                @if ($token['revoked_at'] === '')
                                    <form method="post" action="/admin/tokens/{{ $token['id'] }}/revoke" class="md-inline-form"
                                          data-md-confirm="Отозвать токен «{{ $token['name'] }}»?">
                                        <input type="hidden" name="_token" value="{{ $csrf }}">
                                        <x-md.button type="submit" variant="danger" size="sm" icon="ban">Отозвать</x-md.button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        @endif
    </x-md.table>
</div>
