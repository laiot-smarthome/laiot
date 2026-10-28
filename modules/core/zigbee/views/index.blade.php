@php
    /** @var array<string, mixed> $counts */
    $hasBridges = $bridges !== [];
    $bridgeCount = (int)($counts['bridges'] ?? 0);
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Интеграции"
        icon="network"
        title="Zigbee"
        lede="Устройства Zigbee2MQTT на общем MQTT-соединении: обнаружение, привязки к свойствам, команды и мониторинг."
        :meta="[
            ['icon' => 'router', 'label' => 'Экземпляры', 'value' => (string)$bridgeCount],
            ['icon' => 'device-desktop', 'label' => 'Добавлено', 'value' => (string)($counts['added'] ?? 0)],
            ['icon' => 'search', 'label' => 'Обнаружено', 'value' => (string)($counts['discovered'] ?? 0)],
            ['icon' => 'activity', 'label' => 'В сети', 'value' => (string)($counts['online'] ?? 0) . ' / ' . (string)($counts['offline'] ?? 0)],
        ]">
        <x-slot:actions>
            <x-md.button :href="'/module/zigbee/bridges'" variant="primary" icon="router">Экземпляры</x-md.button>
            <x-md.button :href="'/module/zigbee/monitoring'" variant="secondary" icon="activity">Мониторинг</x-md.button>
            <x-md.button :href="'/module/zigbee/journal'" variant="ghost" icon="history">Журнал</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    @if (!$hasBridges)
        <x-md.hint
            tone="info"
            title="Сначала добавьте экземпляр Zigbee2MQTT"
            text="Экземпляр — это корневой топик, например zigbee2mqtt. Все экземпляры используют авторизацию и TLS модуля MQTT; отдельного соединения Zigbee нет."
            :link="'/module/zigbee/bridges'"
            link-label="Добавить экземпляр" />
    @else
        <div class="md-ui-actions">
            @foreach ($bridges as $bridge)
                <x-md.badge :tone="(int)$bridge['enabled'] === 1 ? 'success' : 'neutral'" dot>
                    {{ $bridge['title'] }} · {{ $bridge['base_topic'] }} · {{ $bridge['state'] }}
                </x-md.badge>
            @endforeach
        </div>
    @endif

    @if (!$health['enabled'])
        <x-md.hint
            tone="warning"
            title="Мониторинг выключен"
            text="Доступность и батареи не проверяются, инциденты не создаются. Включите мониторинг в общих настройках." />
    @endif

    @if ($hasBridges)
        @yoyo('zigbee-devices', ['status' => $filters['status']], ['id' => 'zigbee-devices'])
    @endif
</div>
