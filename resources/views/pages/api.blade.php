<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Система"
        icon="api"
        title="API-документация"
        lede="Живой список маршрутов ядра из Router. Внешние вызовы — Bearer или X-API-Key, локальная сеть работает без токена."
        :meta="[
            ['icon' => 'api', 'label' => 'Маршрутов', 'value' => count($routes)],
        ]">
        <x-slot:actions>
            <x-md.button href="/api" variant="secondary" icon="webhook">Webhook</x-md.button>
            <x-md.button href="/admin/tokens" variant="secondary" icon="key">Токены</x-md.button>
            <x-md.button href="/api/v1/health" variant="ghost" icon="heartbeat">Проверить health</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    <x-md.hint tone="info" title="Как авторизоваться"
               :steps="[
                   'Запросы из локальной сети проходят без токена по правилам API.',
                   'Внешний запрос передаёт Authorization: Bearer <token> или X-API-Key: <token>.',
                   'Токен выпускается в разделе «API-токены» и показывается один раз.',
                   'Небезопасные методы требуют токен и проверку прав пользователя.',
               ]" />

    <x-md.table label="Маршруты API">
        @if ($routes === [])
            <x-slot:empty>
                <div class="md-empty">
                    <span class="md-empty__icon"><i class="ti ti-api" aria-hidden="true"></i></span>
                    <div class="md-empty__title">Маршрутов API нет</div>
                    <p class="md-empty__text">API-маршруты регистрируются в routes/api.php и модулях.</p>
                </div>
            </x-slot:empty>
        @else
            <x-slot:head>
                <th scope="col">Метод</th>
                <th scope="col">URL</th>
                <th scope="col">Имя</th>
            </x-slot:head>
            <x-slot:body>
                @foreach ($routes as $route)
                    <tr>
                        <td>
                            <div class="md-ui-actions">
                                @foreach (explode(', ', $route['methods']) as $method)
                                    <x-md.badge :tone="in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? 'warning' : 'primary'">
                                        {{ $method }}
                                    </x-md.badge>
                                @endforeach
                            </div>
                        </td>
                        <td><code class="md-table__code">{{ $route['uri'] }}</code></td>
                        <td><span class="md-table__muted">{{ $route['name'] !== '' ? $route['name'] : '—' }}</span></td>
                    </tr>
                @endforeach
            </x-slot:body>
        @endif
    </x-md.table>
</div>
