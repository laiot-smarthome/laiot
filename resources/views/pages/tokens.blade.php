<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="key" /> core · API</div>
            <h1 class="md-ui-hero__title">API-токены</h1>
            <p class="md-ui-hero__lede">Bearer/X-API-Key. В базе хранится только SHA-256, токен показывается один раз.</p>
        </div>
    </section>

    @if ($error !== null)
        <x-md.alert type="danger" :text="$error" />
    @endif
    @if ($issued !== null)
        <x-md.alert type="success" title="Токен выпущен" :text="$issued" />
    @endif

    <x-md.card title="Выпустить токен">
        <form method="post" action="/admin/tokens" class="md-form md-form--inline">
            <input type="hidden" name="_token" value="{{ $csrf }}">
            <x-md.select name="user_id" label="Пользователь" required>
                @foreach ($users as $user)
                    <option value="{{ $user['id'] }}">{{ $user['name'] }} ({{ $user['username'] }})</option>
                @endforeach
            </x-md.select>
            <x-md.input name="name" label="Имя токена" required placeholder="monitoring" />
            <x-md.input name="days" type="number" label="Срок, дней" value="0" hint="0 — без срока" />
            <x-md.button type="submit" icon="key">Выпустить</x-md.button>
        </form>
    </x-md.card>

    <x-md.card title="Токены" :subtitle="'Всего: ' . count($tokens)">
        @if ($tokens === [])
            <x-md.empty-state title="Токенов нет" text="Выпустите первый токен для API." icon="key" />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead><tr><th>ID</th><th>Имя</th><th>Пользователь</th><th>Создан</th><th>Использован</th><th>Статус</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($tokens as $token)
                        <tr>
                            <td>{{ $token['id'] }}</td>
                            <td><code>{{ $token['name'] }}</code></td>
                            <td>{{ $token['user_name'] }}</td>
                            <td class="md-table__muted">{{ $token['created_at'] }}</td>
                            <td class="md-table__muted">{{ $token['last_used_at'] !== '' ? $token['last_used_at'] : '—' }}</td>
                            <td>
                                @if ($token['revoked_at'] !== '')
                                    <x-md.badge tone="danger">отозван</x-md.badge>
                                @else
                                    <x-md.badge tone="success">активен</x-md.badge>
                                @endif
                            </td>
                            <td>
                                @if ($token['revoked_at'] === '')
                                    <form method="post" action="/admin/tokens/{{ $token['id'] }}/revoke">
                                        <input type="hidden" name="_token" value="{{ $csrf }}">
                                        <x-md.button type="submit" variant="danger" size="sm" icon="ban">Отозвать</x-md.button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-md.card>
</div>
