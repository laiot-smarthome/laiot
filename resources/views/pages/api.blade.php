<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="api" /> core · api</div>
            <h1 class="md-ui-hero__title">API</h1>
            <p class="md-ui-hero__lede">Реальные маршруты Router. Токены — в <a href="/admin/tokens">/admin/tokens</a>.</p>
        </div>
    </section>

    <x-md.card title="Маршруты" :subtitle="'Всего: ' . count($routes)">
        <div class="md-table-wrap">
            <table class="md-table">
                <thead><tr><th>Метод</th><th>URL</th><th>Имя</th></tr></thead>
                <tbody>
                @foreach ($routes as $route)
                    <tr>
                        <td><code>{{ $route['methods'] }}</code></td>
                        <td><code>{{ $route['uri'] }}</code></td>
                        <td class="md-table__muted">{{ $route['name'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-md.card>
</div>
