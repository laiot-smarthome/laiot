@php
    /** @var array<string, mixed> $settings */
    $points = $points ?? '';
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Интеграции · Zigbee"
        icon="activity"
        title="Мониторинг Zigbee"
        lede="Доступность брокера, экземпляров и добавленных устройств, заряд батарей и правила событий. Устройство может переопределить настройки на своей карточке."
        :meta="[
            ['icon' => 'router', 'label' => 'Экземпляры', 'value' => (string)($counts['bridges'] ?? 0)],
            ['icon' => 'device-desktop', 'label' => 'Добавлено устройств', 'value' => (string)($counts['added'] ?? 0)],
        ]">
        <x-slot:actions>
            <x-md.button href="/module/zigbee" variant="secondary" icon="arrow-left">К устройствам</x-md.button>
            <x-md.button href="/module/zigbee/journal" variant="ghost" icon="history">Журнал</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    <form method="post" action="/module/zigbee/monitoring" class="md-card">
        <input type="hidden" name="_token" value="{{ $csrf }}">
        <header class="md-card__head">
            <div class="md-card__titles">
                <div class="md-card__eyebrow">Общие настройки</div>
                <h2 class="md-card__title">Пороги и события</h2>
                <p class="md-card__subtitle">Проверка выполняется задачей monitor раз в минуту в worker-е Zigbee.</p>
            </div>
        </header>
        <div class="md-card__body">
            @include('zigbee::partials.health', ['settings' => $settings, 'defaults' => $defaults, 'points' => $points])
        </div>
        <div class="md-card__body">
            <x-md.hint tone="info" title="Как это работает"
                       text="При недоступном MQTT-цикле или брокере события устройств подавляются, чтобы не рассылать лавину. Повторный проход не дублирует уже отправленные уведомления; PHP-обработчик получает $params." />
            <div class="md-ui-actions">
                <x-md.button type="submit" variant="primary" icon="device-floppy">Сохранить настройки</x-md.button>
            </div>
        </div>
    </form>
</div>
