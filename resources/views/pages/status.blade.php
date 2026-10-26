<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="activity" /> MyDoMo · core</div>
            <h1 class="md-ui-hero__title">Статус системы</h1>
            <p class="md-ui-hero__lede">Проверка нового ядра: БД, кэш, модули и окружение.</p>
        </div>
    </section>

    <div class="md-ui-kpi-grid">
        <x-md.kpi label="Версия" :value="$version" icon="tag" tone="info" meta="MyDoMo" />
        <x-md.kpi label="PHP" :value="$php" icon="code" tone="neutral" meta="Runtime" />
        <x-md.kpi label="База" :value="$databaseOk ? 'ok' : 'off'" icon="database" :tone="$databaseOk ? 'success' : 'danger'" meta="DatabaseManager" />
        <x-md.kpi label="Кэш" :value="$cacheOk ? 'ok' : 'off'" icon="bolt" :tone="$cacheOk ? 'success' : 'danger'" meta="CacheStore" />
    </div>

    <x-md.card title="Core-модули" :subtitle="'Подключено: ' . $modules">
        <x-md.alert type="info" title="Новый kernel" text="Страница собрана через layouts.alpha и Blade-компоненты без нового дизайна." />
    </x-md.card>
</div>
