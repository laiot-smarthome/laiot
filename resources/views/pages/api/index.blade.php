<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Система"
        icon="webhook"
        title="Входящие webhook"
        lede="Внешний сервис вызывает URL с секретом и запускает сценарий или PHP-код. Секрет хранится только в виде SHA-256."
        :meta="[
            ['icon' => 'webhook', 'label' => 'Входов', 'value' => count($webhooks)],
        ]">
        <x-slot:actions>
            <x-md.button href="/admin/api" variant="secondary" icon="book">Документация API</x-md.button>
            <x-md.button href="/admin/tokens" variant="secondary" icon="key">Токены</x-md.button>
            <x-md.button href="/api/create" icon="plus">Новый webhook</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    @if ($message !== '')
        <x-md.alert type="success" :text="$message" />
    @endif

    @if ($newSecret !== null)
        <x-md.alert type="warning" title="Секрет создан и показывается один раз"
                    text="Сохраните его сейчас: целиком он больше не отображается." />
        <x-md.card title="URL для вызова" subtitle="Передайте секрет в URL; методы и обработчик заданы в webhook">
            <code class="md-table__code">/api/v1/webhooks/{{ $newSecret }}</code>
        </x-md.card>
    @endif

    <x-md.table label="Настроенные входы">
        @if ($webhooks === [])
            <x-slot:empty>
                <div class="md-empty">
                    <span class="md-empty__icon"><i class="ti ti-webhook" aria-hidden="true"></i></span>
                    <div class="md-empty__title">Webhook пока нет</div>
                    <p class="md-empty__text">Создайте вход, чтобы внешний сервис мог запускать сценарий или PHP-код.</p>
                    <div class="md-empty__actions">
                        <a class="md-btn md-btn--primary" href="/api/create">
                            <i class="ti ti-plus" aria-hidden="true"></i><span class="md-btn__label">Новый webhook</span>
                        </a>
                    </div>
                </div>
            </x-slot:empty>
        @else
            <x-slot:head>
                <th scope="col">Название</th>
                <th scope="col">Секрет</th>
                <th scope="col">Обработчик</th>
                <th scope="col">Методы</th>
                <th scope="col">Последний вызов</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>
            <x-slot:body>
                @foreach ($webhooks as $webhook)
                    <tr>
                        <td>
                            <div class="md-table__name">
                                <span class="md-table__icon"><i class="ti ti-webhook" aria-hidden="true"></i></span>
                                <div>
                                    <a href="/api/{{ $webhook['id'] }}/edit"><strong>{{ $webhook['name'] }}</strong></a>
                                    <small>
                                        {{ $webhook['enabled'] ? 'включён' : 'выключен' }}
                                        @if ($webhook['last_status'] !== null) · HTTP {{ $webhook['last_status'] }} @endif
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td><code class="md-table__code">{{ $webhook['secret_preview'] }}</code></td>
                        <td>
                            {{ $webhook['handler_label'] }}
                            @if ($webhook['handler_type'] === 'script')
                                <div class="md-table__muted">сценарий #{{ $webhook['script_id'] }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="md-ui-actions">
                                @foreach ($webhook['methods'] as $method)
                                    <x-md.badge tone="outline">{{ $method }}</x-md.badge>
                                @endforeach
                            </div>
                        </td>
                        <td><span class="md-table__muted">{{ $webhook['last_used_at'] ?? '—' }}</span></td>
                        <td>
                            <div class="md-table__actions">
                                <x-md.icon-button href="/api/{{ $webhook['id'] }}/edit" icon="pencil" label="Изменить webhook" size="sm" />
                                <form method="post" action="/api/{{ $webhook['id'] }}/rotate" class="md-inline-form"
                                      data-md-confirm="Перевыпустить секрет? Старый перестанет работать.">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.icon-button type="submit" icon="refresh" label="Перевыпустить секрет" size="sm" />
                                </form>
                                <form method="post" action="/api/{{ $webhook['id'] }}/delete" class="md-inline-form"
                                      data-md-confirm="Удалить webhook?">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.icon-button type="submit" icon="trash" label="Удалить webhook" variant="danger" size="sm" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        @endif
    </x-md.table>
</div>
