<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="webhook" /> core · api</div>
            <h1 class="md-ui-hero__title">Входящие webhook</h1>
            <p class="md-ui-hero__lede">Секрет в URL, выбранные HTTP-методы и запуск сценария или PHP-кода. Старый <code>HookStore</code> не используется.</p>
        </div>
        <div class="md-ui-actions">
            <x-md.button href="/admin/api" variant="secondary" icon="book">Документация API</x-md.button>
            <x-md.button href="/admin/tokens" variant="secondary" icon="key">API-токены</x-md.button>
            <x-md.button href="/module/api/create" icon="plus">Новый webhook</x-md.button>
        </div>
    </section>

    @if ($message !== '')
        <x-md.alert type="info" :text="$message" />
    @endif

    @if ($newSecret !== null)
        <x-md.alert type="success" title="Секрет создан" :text="'Сохраните его сейчас: целиком он больше не показывается.'" />
        <x-md.card title="URL для вызова">
            <code class="md-code">/api/v1/webhooks/{{ $newSecret }}</code>
            <p class="md-muted">Передайте секрет в URL. Методы и обработчик — ниже в списке.</p>
        </x-md.card>
    @endif

    @if ($webhooks === [])
        <x-md.empty-state title="Webhook пока нет" text="Создайте вход, чтобы внешний сервис мог запускать сценарий или PHP-код." icon="webhook" />
    @else
        <x-md.card title="Настроенные входы">
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead>
                    <tr>
                        <th>Название</th>
                        <th>Секрет</th>
                        <th>Обработчик</th>
                        <th>Методы</th>
                        <th>Статус</th>
                        <th>Последний вызов</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($webhooks as $webhook)
                        <tr>
                            <td>
                                <strong>{{ $webhook['name'] }}</strong>
                                @if ($webhook['last_status'] !== null)
                                    <div class="md-muted">HTTP {{ $webhook['last_status'] }}</div>
                                @endif
                            </td>
                            <td><code>{{ $webhook['secret_preview'] }}</code></td>
                            <td>
                                {{ $webhook['handler_label'] }}
                                @if ($webhook['handler_type'] === 'script')
                                    <div class="md-muted">ID {{ $webhook['script_id'] }}</div>
                                @endif
                            </td>
                            <td>
                                @foreach ($webhook['methods'] as $method)
                                    <x-md.badge tone="info">{{ $method }}</x-md.badge>
                                @endforeach
                            </td>
                            <td>
                                <x-md.badge :tone="$webhook['enabled'] ? 'success' : 'outline'">
                                    {{ $webhook['enabled'] ? 'включён' : 'выключен' }}
                                </x-md.badge>
                            </td>
                            <td class="md-muted">{{ $webhook['last_used_at'] ?? '—' }}</td>
                            <td>
                                <div class="md-ui-actions">
                                    <x-md.button :href="'/module/api/' . $webhook['id'] . '/edit'" variant="ghost" size="sm" icon="edit">Изменить</x-md.button>
                                    <form method="post" action="/module/api/{{ $webhook['id'] }}/rotate" onsubmit="return confirm('Перевыпустить секрет? Старый перестанет работать.');">
                                        <input type="hidden" name="_token" value="{{ $csrf }}">
                                        <x-md.button type="submit" variant="ghost" size="sm" icon="refresh">Секрет</x-md.button>
                                    </form>
                                    <form method="post" action="/module/api/{{ $webhook['id'] }}/delete" onsubmit="return confirm('Удалить webhook?');">
                                        <input type="hidden" name="_token" value="{{ $csrf }}">
                                        <x-md.button type="submit" variant="danger" size="sm" icon="trash">Удалить</x-md.button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </x-md.card>
    @endif
</div>
