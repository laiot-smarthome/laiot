@php
    /** @var array<string, mixed> $device */
    $deviceId = (int)$device['id'];
    $title = trim((string)$device['title']) !== '' ? (string)$device['title'] : (string)$device['friendly_name'];
    $available = (string)$device['available'];
    $availability = (string)($state['current'] ?? '');
    $roomsById = [];
    foreach ($rooms as $room) {
        $roomsById[(int)$room['id']] = (string)$room['title'];
    }
    $format = static fn (string $value, int $limit = 120): string => $value === ''
        ? '—'
        : (mb_strlen($value, 'UTF-8') > $limit ? mb_substr($value, 0, $limit, 'UTF-8') . '…' : $value);
@endphp

<div class="md-ui-page md-ui-page--stack">
    <header class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="network" />Zigbee · устройство</div>
            <h1 class="md-ui-hero__title">{{ $title }}</h1>
            <p class="md-ui-hero__lede"><code class="md-table__code">{{ $device['friendly_name'] }}</code></p>
            <div class="md-ui-hero__meta">
                <span class="md-ui-hero__meta-item"><x-md.icon name="router" /><span>Экземпляр</span><strong>{{ $bridge['title'] ?? '—' }}</strong></span>
                <span class="md-ui-hero__meta-item"><x-md.icon name="barcode" /><span>IEEE</span><strong>{{ $device['ieee_address'] ?: '—' }}</strong></span>
                <span class="md-ui-hero__meta-item"><x-md.icon name="cpu" /><span>Модель</span><strong>{{ $device['model'] !== '' ? $device['model'] : '—' }}</strong></span>
                <span class="md-ui-hero__meta-item"><x-md.icon name="activity" /><span>Связь</span><strong>{{ $available === 'online' ? 'в сети' : ($available === 'offline' ? 'не в сети' : 'неизвестно') }}</strong></span>
            </div>
        </div>
        <div class="md-ui-hero__actions">
            <x-md.button href="/module/zigbee" variant="secondary" icon="arrow-left">К устройствам</x-md.button>
            @if ((int)$device['location_id'] > 0)
                <x-md.button :href="'/locations/' . (int)$device['location_id'] . '/edit'" variant="ghost" icon="map-pin">Комната</x-md.button>
            @endif
            <form method="post" action="/module/zigbee/devices/{{ $deviceId }}/delete" class="md-inline-form"
                  data-md-confirm="Удалить устройство, его свойства и привязки? Отменить это нельзя.">
                <input type="hidden" name="_token" value="{{ $csrf }}">
                <x-md.button type="submit" variant="danger" icon="trash">Удалить</x-md.button>
            </form>
        </div>
    </header>

    @if ((string)$device['status'] !== 'added')
        <x-md.hint tone="warning" title="Устройство только обнаружено"
                   text="Обработчики, команды и уведомления комнаты не выполняются. Добавьте устройство из списка «Обнаруженные»." />
    @endif

    <form method="post" action="/module/zigbee/devices/{{ $deviceId }}" class="md-card">
        <input type="hidden" name="_token" value="{{ $csrf }}">
        <header class="md-card__head">
            <div class="md-card__titles">
                <div class="md-card__eyebrow">Устройство</div>
                <h2 class="md-card__title">Название и комната</h2>
                <p class="md-card__subtitle">Название — подпись панели; имя Zigbee2MQTT остаётся ключом устройства.</p>
            </div>
        </header>
        <div class="md-card__body">
            <div class="md-ui-form-grid">
                <x-md.input name="title" label="Название" :value="(string)$device['title']" placeholder="{{ $device['friendly_name'] }}" />
                <x-md.select name="location_id" label="Комната" :selected="(string)(int)$device['location_id']"
                             :options="array_merge(['0' => 'Без комнаты'], $roomsById)" />
            </div>
            <div class="md-ui-actions">
                <x-md.button type="submit" variant="primary" icon="device-floppy">Сохранить</x-md.button>
            </div>
        </div>
    </form>

    <section class="md-card">
        <header class="md-card__head">
            <div class="md-card__titles">
                <div class="md-card__eyebrow">Свойства</div>
                <h2 class="md-card__title">Данные устройства</h2>
                <p class="md-card__subtitle">Исходное значение приходит из сообщения, преобразованное — запись привязки.</p>
            </div>
        </header>
        <div class="md-table-wrap">
            @if ($properties === [])
                <x-md.empty-state icon="list" title="Свойств пока нет"
                                  text="Они появятся из bridge/devices (exposes) или первого сообщения устройства." compact />
            @else
                <div class="md-table__scroll">
                    <table class="md-table">
                        <thead>
                        <tr>
                            <th>Свойство</th>
                            <th>Исходное</th>
                            <th>После приёма</th>
                            <th>Привязка</th>
                            <th>Запись</th>
                            <th>Ошибка</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($properties as $property)
                            @php
                                $bindingLabel = '';
                                if ($property['linked_object'] !== '') {
                                    $bindingLabel = $property['linked_object'];
                                    if ($property['linked_property'] !== '') {
                                        $bindingLabel .= '.' . $property['linked_property'];
                                    } elseif ($property['linked_method'] !== '') {
                                        $bindingLabel .= '.' . $property['linked_method'];
                                    }
                                }
                                $raw = (string)($property['raw_value'] ?? '');
                                $value = (string)($property['value'] ?? '');
                            @endphp
                            <tr>
                                <td>
                                    <a href="/module/zigbee/properties/{{ (int)$property['id'] }}"><strong>{{ $property['path'] }}</strong></a>
                                    @if ($property['label'] !== '' && $property['label'] !== $property['path'])
                                        <div class="md-table__muted">{{ $property['label'] }}</div>
                                    @endif
                                </td>
                                <td><code class="md-table__code" title="{{ $raw }}">{{ $format($raw, 60) }}</code></td>
                                <td><code class="md-table__code" title="{{ $value }}">{{ $format($value, 60) }}</code></td>
                                <td>{{ $bindingLabel !== '' ? $bindingLabel : '—' }}</td>
                                <td>
                                    <x-md.badge :tone="$property['write_enabled'] ? 'success' : 'neutral'">
                                        {{ $property['write_enabled'] ? 'включена' : 'выключена' }}
                                    </x-md.badge>
                                </td>
                                <td>
                                    @if ((string)$property['last_error'] !== '')
                                        <x-md.badge tone="danger">{{ $format((string)$property['last_error'], 80) }}</x-md.badge>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    <section class="md-card" id="monitoring">
        <header class="md-card__head">
            <div class="md-card__titles">
                <div class="md-card__eyebrow">Мониторинг</div>
                <h2 class="md-card__title">Переопределение для устройства</h2>
                <p class="md-card__subtitle">
                    @if ($healthInherited)
                        Сейчас действуют общие настройки. Снятие галочки ниже сохранит собственные.
                    @else
                        Действуют собственные настройки устройства.
                    @endif
                </p>
            </div>
        </header>
        <form method="post" action="/module/zigbee/devices/{{ $deviceId }}/health">
            <input type="hidden" name="_token" value="{{ $csrf }}">
            <div class="md-card__body">
                @include('zigbee::partials.health', ['settings' => $health, 'defaults' => $healthDefaults, 'points' => \MajorDoMo\Zigbee\Support\RuleForm::pointsText($health)])
            </div>
            <div class="md-card__body">
                <x-md.checkbox name="inherit" label="Использовать общие настройки" value="1" :checked="$healthInherited"
                               description="Собственные пороги и события будут удалены." />
                <div class="md-ui-actions">
                    <x-md.button type="submit" variant="primary" icon="device-floppy">Сохранить мониторинг</x-md.button>
                </div>
            </div>
        </form>
        @unless ($healthInherited)
            <div class="md-card__body">
                <form method="post" action="/module/zigbee/devices/{{ $deviceId }}/health/inherit">
                    <input type="hidden" name="_token" value="{{ $csrf }}">
                    <x-md.button type="submit" variant="ghost" icon="refresh">Вернуть общие настройки</x-md.button>
                </form>
            </div>
        @endunless
    </section>

    @if ($availability !== '')
        <x-md.hint tone="info" title="Состояние доступности"
                   :text="'Инцидент: ' . $availability . '. Обновлено: ' . ((string)($state['changed_at'] ?? '') !== '' ? date('d.m.Y H:i', (int)$state['changed_at']) : '—')" />
    @endif
</div>
