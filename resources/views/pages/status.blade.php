<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Обзор"
        icon="activity"
        title="Состояние системы"
        lede="Проверка нового ядра: база, кэш, версия и подключённые SDK-модули."
        :meta="[
            ['icon' => 'tag', 'label' => 'Версия', 'value' => $version],
            ['icon' => 'code', 'label' => 'PHP', 'value' => $php],
        ]" />

    <div class="md-ui-kpi-grid">
        <x-md.kpi label="База данных" :value="$databaseOk ? 'доступна' : 'недоступна'" icon="database"
                  :tone="$databaseOk ? 'success' : 'danger'" meta="DatabaseManager, ping SELECT 1" />
        <x-md.kpi label="Кэш" :value="$cacheOk ? 'работает' : 'недоступен'" icon="bolt"
                  :tone="$cacheOk ? 'success' : 'danger'" meta="CacheStore: запись и чтение" />
        <x-md.kpi label="SDK-модули" :value="(string)$modules" icon="puzzle" tone="info"
                  meta="MQTT, Zigbee и сторонние" />
        <x-md.kpi label="Ядровые разделы" value="ядро" icon="layout-dashboard" tone="neutral"
                  meta="Не являются модулями" />
    </div>

    <x-md.card title="Что проверяет эта страница">
        <ul class="md-ui-list">
            <li><i class="ti ti-check" aria-hidden="true"></i><span>Соединение с БД открывается и выполняет запрос.</span></li>
            <li><i class="ti ti-check" aria-hidden="true"></i><span>Кэш принимает запись и возвращает то же значение.</span></li>
            <li><i class="ti ti-check" aria-hidden="true"></i><span>SDK-модули обнаруживаются и регистрируются в роутере.</span></li>
        </ul>
        <p class="md-ui-muted">
            Машиночитаемая проверка — <a href="/api/v1/health">/api/v1/health</a>,
            подробный журнал — <a href="/errors">Ошибки</a>, фоновые задания — <a href="/jobs">Очередь</a>.
        </p>
    </x-md.card>
</div>
