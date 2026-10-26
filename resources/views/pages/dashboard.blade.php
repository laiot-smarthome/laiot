<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="layout-dashboard" /> MyDoMo · web</div>
            <h1 class="md-ui-hero__title">Обзор</h1>
            <p class="md-ui-hero__lede">Админка на Blade-компонентах и alpha-дизайне. Циклы пока не запускаются.</p>
        </div>
    </section>

    <div class="md-ui-kpi-grid">
        <x-md.kpi label="Версия" :value="$version" icon="tag" tone="info" meta="MyDoMo" />
        <x-md.kpi label="PHP" :value="$php" icon="code" tone="neutral" meta="Runtime" />
        <x-md.kpi label="База" :value="$databaseOk ? 'ok' : 'off'" icon="database" :tone="$databaseOk ? 'success' : 'danger'" meta="DatabaseManager" />
        <x-md.kpi label="Кэш" :value="$cacheOk ? 'ok' : 'off'" icon="bolt" :tone="$cacheOk ? 'success' : 'danger'" meta="CacheStore" />
    </div>

    <x-md.card title="Модули" :subtitle="'Подключено: ' . count($modules)">
        @if ($modules === [])
            <x-md.empty-state title="Пока нет модулей" text="Скоро здесь появятся core-модули." icon="puzzle" />
        @else
            <div class="md-ui-card-grid">
                @foreach ($modules as $id => $module)
                    <a class="md-card md-card--link" href="/module/{{ $id }}">
                        <div class="md-card__body">
                            <div class="md-ui-hero__eyebrow"><x-md.icon name="puzzle" /> {{ $module['type'] ?? 'core' }}</div>
                            <h3 class="md-card__title">{{ $module['name'] ?? $id }}</h3>
                            <p class="md-card__subtitle">v{{ $module['version'] ?? '0.0.0' }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </x-md.card>

    <x-md.card title="Быстрые действия">
        <div class="md-ui-actions">
            <x-md.button href="/status" variant="secondary" icon="activity">Статус</x-md.button>
            <x-md.button href="/roles" variant="secondary" icon="shield">Роли</x-md.button>
            <x-md.button href="/api/v1/health" variant="ghost" icon="heartbeat">Health JSON</x-md.button>
        </div>
        <p class="md-muted">Часовой пояс: {{ $timezone }}. Фоновые процессы и циклы пока не трогаем.</p>
    </x-md.card>
</div>
