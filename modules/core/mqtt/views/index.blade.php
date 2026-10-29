@php
    /** @var array<string, mixed> $settings */
    $connected = (bool)($status['connected'] ?? false);
    $alive = (bool)($status['worker_alive'] ?? false);
    $broker = (string)($settings['host'] ?? '') . ':' . (string)($settings['port'] ?? '');
@endphp

<div class="md-ui-page md-ui-page--stack md-mqtt-page" data-md-mqtt-live>
    <x-md.page-hero
        eyebrow="Интеграции"
        icon="radio"
        title="MQTT"
        lede="Одно долгоживущее соединение с брокером: приём сообщений, привязки к свойствам объектов и мгновенная отправка команд."
        :meta="[
            ['icon' => 'router', 'label' => 'Брокер', 'value' => $broker],
            ['icon' => 'filter', 'label' => 'Подписки', 'value' => implode(', ', $queries)],
            ['icon' => 'lock', 'label' => 'Авторизация', 'value' => !empty($settings['auth']) ? 'да' : 'нет'],
            ['icon' => 'shield-lock', 'label' => 'TLS', 'value' => !empty($settings['tls']) ? 'включён' : 'выключен'],
        ]">
        <x-slot:actions>
            <x-md.button :href="'/module/mqtt/topics/create'" variant="primary" icon="plus">Добавить топик</x-md.button>
            <x-md.button :href="'/module/mqtt/settings'" variant="secondary" icon="settings">Настройки</x-md.button>
            <form method="post" action="/module/mqtt/topics/prune-history" class="md-inline-form"
                  data-md-confirm="Удалить историю MQTT старше {{ (int)($settings['history_keep_days'] ?? 30) }} дней?">
                <input type="hidden" name="_token" value="{{ $csrf }}">
                <x-md.button type="submit" variant="ghost" icon="history" size="sm">История</x-md.button>
            </form>
            <form method="post" action="/module/mqtt/topics/clear-trash" class="md-inline-form"
                  data-md-confirm="Удалить все топики без привязки к объекту?">
                <input type="hidden" name="_token" value="{{ $csrf }}">
                <x-md.button type="submit" variant="ghost" icon="trash" size="sm">Мусор</x-md.button>
            </form>
        </x-slot:actions>
    </x-md.page-hero>


    <div class="md-mqtt-connection">
        <x-md.badge :tone="$connected ? 'success' : 'warning'" dot>
            {{ $connected ? 'брокер подключён' : 'нет соединения' }}
        </x-md.badge>
        <x-md.badge :tone="$alive ? 'success' : 'danger'" dot>
            {{ $alive ? 'worker mqtt запущен' : 'worker mqtt не запущен' }}
        </x-md.badge>
        @if ($alive && !empty($status['worker_pid']))
            <span class="md-table__muted">PID {{ (int)$status['worker_pid'] }}</span>
        @endif
        <span class="md-table__muted">Состояние обновляется автоматически; правки в карточке топика сохраняются отдельным POST.</span>
    </div>

    @if (!$alive)
        <x-md.hint
            tone="warning"
            title="Фоновый процесс не запущен"
            text="Приём и отправка работают только при запущенном worker: php bin/laiot worker mqtt (systemd: laiot-worker@mqtt). Без него значения не обновляются, а команды ждут в очереди module.mqtt." />
    @endif

    @yoyo('mqtt-status', [], ['id' => 'mqtt-status'])

    @yoyo('mqtt-topics', [], ['id' => 'mqtt-topics'])
</div>

@include('mqtt::partials.live')
