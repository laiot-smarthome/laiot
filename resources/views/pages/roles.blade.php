<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="shield" /> core · auth</div>
            <h1 class="md-ui-hero__title">Роли и права</h1>
            <p class="md-ui-hero__lede">Роли, permissions и проверка can:&lt;permission&gt;.</p>
        </div>
    </section>

    <div class="md-ui-kpi-grid">
        <x-md.kpi label="Роли" :value="(string)count($roles)" icon="users" tone="info" meta="roles" />
        <x-md.kpi label="Права" :value="(string)count($permissions)" icon="key" tone="neutral" meta="permissions" />
    </div>

    <x-md.card title="Роли">
        <div class="md-table-wrap">
            <table class="md-table">
                <thead><tr><th>ID</th><th>Код</th><th>Название</th></tr></thead>
                <tbody>
                @foreach ($roles as $role)
                    <tr><td>{{ $role['id'] }}</td><td><code>{{ $role['name'] }}</code></td><td>{{ $role['title'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-md.card>

    <x-md.card title="Разрешения">
        <div class="md-table-wrap">
            <table class="md-table">
                <thead><tr><th>ID</th><th>Код</th><th>Название</th></tr></thead>
                <tbody>
                @foreach ($permissions as $permission)
                    <tr><td>{{ $permission['id'] }}</td><td><code>{{ $permission['name'] }}</code></td><td>{{ $permission['title'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-md.card>
</div>
