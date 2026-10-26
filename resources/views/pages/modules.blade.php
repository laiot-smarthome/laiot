<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="puzzle" /> core · modules</div>
            <h1 class="md-ui-hero__title">Модули</h1>
            <p class="md-ui-hero__lede">MQTT, Zigbee и сторонние модули MyDoMo SDK. Ядровые разделы — не модули.</p>
        </div>
    </section>

    @if ($modules === [])
        <x-md.empty-state title="Модулей нет" text="Установите сторонний модуль через module:install или добавьте core-модуль MQTT/Zigbee." icon="puzzle" />
    @else
    <div class="md-ui-card-grid">
        @foreach ($modules as $id => $module)
            <x-md.card :title="$module['name'] ?? $id" :subtitle="'v' . ($module['version'] ?? '0.0.0')">
                <div class="md-ui-stack">
                    <div><x-md.badge tone="info">{{ $module['type'] ?? 'core' }}</x-md.badge></div>
                    <div class="md-muted">ID: <code>{{ $id }}</code></div>
                    <div class="md-muted">Маршрут: <code>/module/{{ $id }}</code></div>
                    @if (!empty($module['storage']['tables']))
                        <div class="md-muted">Таблицы: <code>{{ implode(', ', $module['storage']['tables']) }}</code></div>
                    @endif
                    @if (!empty($module['helpers']))
                        <div class="md-muted">Helpers: <code>{{ is_array($module['helpers']) ? implode(', ', $module['helpers']) : $module['helpers'] }}</code></div>
                    @endif
                    <div><x-md.button :href="'/module/' . $id" variant="secondary" size="sm" icon="arrow-right">Открыть</x-md.button></div>
                </div>
            </x-md.card>
        @endforeach
    </div>
    @endif
</div>
