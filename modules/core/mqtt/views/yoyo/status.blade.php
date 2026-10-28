@php
    $connected = (bool)($status['connected'] ?? false);
    $alive = (bool)($status['worker_alive'] ?? false);
    $counters = is_array($status['counters'] ?? null) ? $status['counters'] : [];
    $lastMessage = (int)($status['last_message_at'] ?? 0);
    $lastError = (string)($status['last_error'] ?? '');
    $lastErrorAt = (int)($status['last_error_at'] ?? 0);
    $errors = (int)($counters['errors'] ?? 0);
    $format = static fn (int $timestamp): string => $timestamp > 0 ? date('d.m.Y H:i:s', $timestamp) : '—';
    $relative = static function (int $timestamp): string {
        if ($timestamp <= 0) {
            return '—';
        }
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'только что';
        }
        if ($diff < 3600) {
            return max(1, (int)floor($diff / 60)) . ' мин назад';
        }
        if ($diff < 86400) {
            return max(1, (int)floor($diff / 3600)) . ' ч назад';
        }

        return date('d.m H:i', $timestamp);
    };
@endphp

<section class="md-card md-mqtt-status {{ $open ? 'is-open' : 'is-collapsed' }}">
    <header class="md-card__head">
        <div class="md-card__titles">
            <div class="md-card__eyebrow">Соединение</div>
            <h2 class="md-card__title"><x-md.icon name="activity" />Состояние цикла</h2>
        </div>
        <div class="md-card__actions md-mqtt-status__actions">
            <x-md.badge :tone="$connected ? 'success' : 'warning'" dot>{{ $connected ? 'подключён' : 'нет связи' }}</x-md.badge>
            <x-md.badge :tone="$alive ? 'success' : 'danger'" dot>{{ $alive ? 'worker жив' : 'worker не запущен' }}</x-md.badge>
            @if ($pending > 0)
                <x-md.badge tone="info">очередь {{ (int)$pending }}</x-md.badge>
            @endif
            @if ($errors > 0)
                <x-md.badge tone="danger">ошибки {{ $errors }}</x-md.badge>
            @endif
            @if ($lastMessage > 0)
                <span class="md-table__muted md-mqtt-status__seen" title="{{ $format($lastMessage) }}">
                    {{ $relative($lastMessage) }}
                </span>
            @endif
            <button type="button" class="md-btn md-btn--ghost md-btn--sm" yoyo:get="refreshStatus"
                    yoyo:spinning.attr="disabled" title="Обновить состояние" aria-label="Обновить состояние">
                <x-md.icon name="refresh" />
            </button>
            <button type="button" class="md-btn md-btn--secondary md-btn--sm" yoyo:get="toggle"
                    aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="md-mqtt-status-body">
                <x-md.icon :name="$open ? 'chevron-up' : 'chevron-down'" />
                <span class="md-btn__label">{{ $open ? 'Свернуть' : 'Подробнее' }}</span>
            </button>
        </div>
    </header>

    @if ($open)
        <div class="md-card__body" id="md-mqtt-status-body">
            <div class="md-mqtt-status__grid">
                <dl class="md-dash-kv">
                    <div><dt>Брокер</dt><dd><code class="md-table__code">{{ (string)($status['broker'] ?? '') }}</code></dd></div>
                    <div><dt>Подписки</dt><dd>{{ $filters === [] ? '—' : implode(', ', $filters) }}</dd></div>
                    <div><dt>Очередь команд</dt><dd>{{ (int)$pending }}</dd></div>
                    <div><dt>Последнее сообщение</dt><dd title="{{ $format($lastMessage) }}">{{ $relative($lastMessage) }}</dd></div>
                </dl>
                <dl class="md-dash-kv">
                    <div><dt>Принято</dt><dd>{{ (int)($counters['received'] ?? 0) }}</dd></div>
                    <div><dt>Применено к свойствам</dt><dd>{{ (int)($counters['bound'] ?? 0) }}</dd></div>
                    <div><dt>Команд отправлено</dt><dd>{{ (int)($counters['published'] ?? 0) }}</dd></div>
                    <div><dt>Ошибок</dt><dd>{{ $errors }}</dd></div>
                </dl>
                <dl class="md-dash-kv">
                    <div><dt>Приём, среднее</dt><dd>{{ number_format((float)($status['inbound_ms'] ?? 0), 1, ',', ' ') }} мс</dd></div>
                    <div><dt>Отправка, среднее</dt><dd>{{ number_format((float)($status['publish_ms'] ?? 0), 1, ',', ' ') }} мс</dd></div>
                    <div><dt>Новых топиков</dt><dd>{{ (int)($counters['discovered'] ?? 0) }}</dd></div>
                    <div><dt>Отдано в очередь</dt><dd>{{ (int)($counters['delivered'] ?? 0) }}</dd></div>
                </dl>
            </div>

            @if ($lastError !== '')
                <div class="md-mqtt-status__error">
                    <x-md.badge tone="danger">последняя ошибка</x-md.badge>
                    <span>{{ $lastError }}</span>
                    <span class="md-table__muted">{{ $format($lastErrorAt) }}</span>
                </div>
            @endif

            @if (!$alive)
                <x-md.hint
                    tone="warning"
                    title="Приём не работает"
                    text="Запустите php bin/mydomo worker mqtt (или mydomo-worker@mqtt). Пока процесс не запущен, брокер не опрашивается, а очередь module.mqtt копится." />
            @endif
        </div>
    @endif
</section>
