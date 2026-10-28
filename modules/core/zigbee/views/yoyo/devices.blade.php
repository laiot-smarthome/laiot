@php
    $rows = $result['rows'];
    $pages = (int)$result['pages'];
    $current = (int)$result['page'];
    $total = (int)$result['total'];
    $perPage = (int)$result['per_page'];
    $from = $total > 0 ? (($current - 1) * $perPage) + 1 : 0;
    $to = min($current * $perPage, $total);
    $roomsById = [];
    foreach ($rooms as $room) {
        $roomsById[(int)$room['id']] = (string)$room['title'];
    }
    $human = static function (string $value): string {
        if ($value === '') {
            return '—';
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '—';
        }
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'только что';
        }
        if ($diff < 86400) {
            return date('H:i:s', $timestamp);
        }

        return date('d.m.Y H:i', $timestamp);
    };
    $statusLabel = ['added' => 'Добавлено', 'discovered' => 'Обнаружено'];
    $availabilityTone = ['online' => 'success', 'offline' => 'danger'];
@endphp

<div class="md-ui-kpi-grid">
    <x-md.kpi label="Всего устройств" :value="$counts['total'] ?? 0" icon="device-desktop" tone="primary" meta="В базе Zigbee" />
    <x-md.kpi label="Добавлено" :value="$counts['added'] ?? 0" icon="circle-check" tone="success" meta="Обрабатывают привязки" />
    <x-md.kpi label="Обнаружено" :value="$counts['discovered'] ?? 0" icon="search" tone="warning" meta="Ждут добавления" />
    <x-md.kpi label="Связь" :value="($counts['online'] ?? 0) . ' / ' . ($counts['offline'] ?? 0)" icon="activity" tone="info" meta="В сети / не в сети" />
</div>

<div class="md-card">
    <header class="md-card__head">
        <div class="md-card__titles">
            <div class="md-card__eyebrow">Устройства</div>
            <h2 class="md-card__title">Zigbee2MQTT</h2>
            <p class="md-card__subtitle">Показано {{ $from }}–{{ $to }} из {{ $total }}. До добавления обработчики устройства не выполняются.</p>
        </div>
        <div class="md-ui-actions">
            <button type="button" class="md-btn md-btn--sm {{ $status === '' ? 'md-btn--primary' : 'md-btn--ghost' }}"
                    yoyo:get="setStatus" yoyo:val.status="" yoyo:spinning.attr="disabled">Все</button>
            <button type="button" class="md-btn md-btn--sm {{ $status === 'added' ? 'md-btn--primary' : 'md-btn--ghost' }}"
                    yoyo:get="setStatus" yoyo:val.status="added" yoyo:spinning.attr="disabled">Добавленные</button>
            <button type="button" class="md-btn md-btn--sm {{ $status === 'discovered' ? 'md-btn--primary' : 'md-btn--ghost' }}"
                    yoyo:get="setStatus" yoyo:val.status="discovered" yoyo:spinning.attr="disabled">Обнаруженные</button>
            <button type="button" class="md-btn md-btn--sm md-btn--ghost" yoyo:get="resetFilters" yoyo:spinning.attr="disabled">
                <x-md.icon name="filter-off" /><span class="md-btn__label">Сбросить</span>
            </button>
        </div>
    </header>
    <div class="md-card__body">
        <div class="md-ui-form-grid">
            <x-md.input name="search" label="Поиск" :value="$search" placeholder="Имя, модель или IEEE"
                        hint="Поиск по имени, названию, модели и адресу."
                        yoyo:on="keyup delay:400ms changed" />
            <x-md.select name="bridge" label="Экземпляр" :selected="$bridge"
                         :options="$bridgeOptions"
                         yoyo:on="change" />
            <x-md.select name="location" label="Комната" :selected="$location"
                         :options="$roomOptions"
                         yoyo:on="change" />
            <x-md.select name="available" label="Связь" :selected="$available"
                         :options="['' => 'Любая', 'online' => 'В сети', 'offline' => 'Не в сети']"
                         yoyo:on="change" />
        </div>
    </div>
</div>

@if ($rows === [])
    <x-md.empty-state icon="network" title="Устройств пока нет"
                      text="Устройства появятся после retained-сообщения bridge/devices или первого сообщения устройства в корневом топике экземпляра." />
@else
    <form method="post" action="/module/zigbee/devices/configure" class="md-card">
        <input type="hidden" name="_token" value="{{ $csrf }}">
        <div class="md-table-wrap">
            <div class="md-table__scroll">
                <table class="md-table">
                    <thead>
                    <tr>
                        <th style="width: 36px"><input type="checkbox" data-md-raw-checkbox aria-label="Выбрать все"></th>
                        <th>Устройство</th>
                        <th>Экземпляр</th>
                        <th>Комната</th>
                        <th>Статус</th>
                        <th>Связь</th>
                        <th>Батарея</th>
                        <th>Последнее сообщение</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $row)
                        @php
                            $deviceId = (int)$row['id'];
                            $state = $states[$deviceId] ?? [];
                            $availability = (string)($state['availability']['current'] ?? '');
                            $battery = $state['battery']['reading'] ?? null;
                            $title = trim((string)$row['title']) !== '' ? (string)$row['title'] : (string)$row['friendly_name'];
                        @endphp
                        <tr>
                            <td><input type="checkbox" data-md-raw-checkbox name="ids[]" value="{{ $deviceId }}" aria-label="Выбрать {{ $title }}"></td>
                            <td>
                                <a href="/module/zigbee/devices/{{ $deviceId }}"><strong>{{ $title }}</strong></a>
                                <div class="md-table__muted">
                                    {{ $row['friendly_name'] }}
                                    @if ($row['model'] !== '') · {{ $row['model'] }} @endif
                                    @if ($row['ieee_address'] !== null && $row['ieee_address'] !== '') · <code>{{ $row['ieee_address'] }}</code> @endif
                                </div>
                            </td>
                            <td>{{ $row['bridge_title'] }}</td>
                            <td>{{ $roomsById[(int)$row['location_id']] ?? '—' }}</td>
                            <td>
                                <x-md.badge :tone="(string)$row['status'] === 'added' ? 'success' : 'warning'">
                                    {{ $statusLabel[(string)$row['status']] ?? $row['status'] }}
                                </x-md.badge>
                            </td>
                            <td>
                                <x-md.badge :tone="$availabilityTone[$availability] ?? 'neutral'" dot>
                                    {{ $availability === 'online' ? 'в сети' : ($availability === 'offline' ? 'не в сети' : '—') }}
                                </x-md.badge>
                            </td>
                            <td>{{ $battery !== null ? $battery . '%' : '—' }}</td>
                            <td>{{ $human((string)($row['last_seen'] ?? '')) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="md-card__body">
            <div class="md-ui-form-grid">
                <x-md.select name="location_id" label="Комната для выбранных" :options="$assignOptions" />
                <x-md.input name="title" label="Название" placeholder="Только для одного устройства"
                            hint="Пусто — оставить имя Zigbee2MQTT. Название задаётся одному устройству." />
            </div>
            <x-md.checkbox name="adopt" label="Добавить устройства" value="1"
                           description="После добавления обрабатываются привязки, команды и уведомления комнаты. Старые показания не воспроизводятся." />
            <div class="md-ui-actions">
                <x-md.button type="submit" variant="primary" icon="plus">Применить к выбранным</x-md.button>
            </div>
        </div>
    </form>

    <div class="md-card">
        <div class="md-card__body">
            <form method="post" action="/module/zigbee/devices/configure-all" class="md-ui-actions">
                <input type="hidden" name="_token" value="{{ $csrf }}">
                <input type="hidden" name="q" value="{{ $search }}">
                <input type="hidden" name="status" value="discovered">
                <input type="hidden" name="bridge" value="{{ $bridge }}">
                <input type="hidden" name="location" value="{{ $location }}">
                <x-md.button type="submit" variant="secondary" icon="checks">Добавить все найденные без комнаты</x-md.button>
                <span class="md-table__muted">До 500 устройств с текущим поиском. Комнату можно назначить позже.</span>
            </form>
        </div>
    </div>

    @if ($pages > 1)
        <div class="md-ui-actions">
            @for ($page = 1; $page <= $pages; $page++)
                <button type="button"
                        class="md-btn md-btn--sm {{ $page === $current ? 'md-btn--primary' : 'md-btn--ghost' }}"
                        yoyo:get="paginate" yoyo:val.page="{{ $page }}" yoyo:spinning.attr="disabled">{{ $page }}</button>
            @endfor
        </div>
    @endif
@endif
