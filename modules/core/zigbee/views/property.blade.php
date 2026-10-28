@php
    /** @var array<string, mixed> $property */
    /** @var array<string, mixed> $device */
    /** @var array<string, mixed>|null $binding */
    $propertyId = (int)$property['id'];
    $deviceId = (int)$device['id'];
    $deviceTitle = trim((string)$device['title']) !== '' ? (string)$device['title'] : (string)$device['friendly_name'];
    $linkedObject = (string)($binding['linked_object'] ?? '');
    $linkedProperty = (string)($binding['linked_property'] ?? '');
    $linkedMethod = (string)($binding['linked_method'] ?? '');
    $bindingEnabled = $binding === null || (int)$binding['enabled'] === 1;
    $writeEnabled = $binding !== null && (int)$binding['write_enabled'] === 1;
    $eventMode = (string)($binding['event_mode'] ?? 'always');
    $raw = (string)($property['raw_value'] ?? '');
    $value = (string)($property['value'] ?? '');
    $access = is_array(json_decode((string)($property['expose'] ?? ''), true))
        ? (json_decode((string)$property['expose'], true)['access'] ?? 0)
        : 0;
    $canWrite = ((int)$access & 2) === 2;
@endphp

<div class="md-ui-page md-ui-page--stack">
    <header class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="network" />Zigbee · свойство</div>
            <h1 class="md-ui-hero__title">{{ $property['path'] }}</h1>
            <p class="md-ui-hero__lede">
                Устройство
                <a href="/module/zigbee/devices/{{ $deviceId }}">{{ $deviceTitle }}</a>
                @if ((string)($bridge['title'] ?? '') !== '') · экземпляр {{ $bridge['title'] }} @endif
            </p>
            <div class="md-ui-hero__meta">
                <span class="md-ui-hero__meta-item"><x-md.icon name="message" /><span>Исходное</span><strong>{{ $raw === '' ? '—' : $raw }}</strong></span>
                <span class="md-ui-hero__meta-item"><x-md.icon name="transform" /><span>После приёма</span><strong>{{ $value === '' ? '—' : $value }}</strong></span>
                <span class="md-ui-hero__meta-item"><x-md.icon name="clock" /><span>Обновлено</span><strong>{{ (string)($property['updated'] ?? '') !== '' ? date('d.m.Y H:i:s', (int)strtotime((string)$property['updated'])) : '—' }}</strong></span>
                <span class="md-ui-hero__meta-item"><x-md.icon name="lock" /><span>Запись</span><strong>{{ $canWrite ? 'разрешена' : 'только чтение' }}</strong></span>
            </div>
        </div>
        <div class="md-ui-hero__actions">
            <x-md.button :href="'/module/zigbee/devices/' . $deviceId" variant="secondary" icon="arrow-left">К устройству</x-md.button>
        </div>
    </header>

    @if ((string)($binding['last_error'] ?? '') !== '')
        <x-md.hint tone="danger" title="Последняя ошибка привязки" :text="(string)$binding['last_error']" />
    @endif

    <form method="post" action="/module/zigbee/properties/{{ $propertyId }}" class="md-card">
        <input type="hidden" name="_token" value="{{ $csrf }}">
        <header class="md-card__head">
            <div class="md-card__titles">
                <div class="md-card__eyebrow">Привязка</div>
                <h2 class="md-card__title">Объект, свойство и метод</h2>
                <p class="md-card__subtitle">Порядок: сообщение → приём → значение объекта → OnChange → дополнительный метод → комната.</p>
            </div>
        </header>
        <div class="md-card__body">
            <x-md.linked-object
                name="linked_object"
                :value="$linkedObject"
                label="Объект"
                propertyName="linked_property"
                :property="$linkedProperty"
                methodName="linked_method"
                :method="$linkedMethod"
                placeholder="Начните вводить имя объекта"
                hint="Очистите объект, чтобы снять привязку. Подписка на свойство ставится при включении записи." />
        </div>
        <div class="md-card__body">
            <div class="md-ui-form-grid">
                <x-md.select name="event_mode" label="Режим события" :selected="$eventMode"
                             :options="['always' => 'Каждое сообщение', 'change' => 'Только изменение']" />
                <div class="md-ui-form-stack">
                    <x-md.switch name="enabled" label="Привязка включена" :checked="$bindingEnabled"
                                 description="Выключенная привязка только сохраняет исходное и преобразованное значение." />
                    <x-md.switch name="write_enabled" label="Отправлять изменения свойства обратно в устройство" :checked="$writeEnabled"
                                 description="Ставит подписку модуля на свойство объекта; эхо самого Zigbee не отправляется." />
                </div>
            </div>
        </div>
        <div class="md-card__body">
            <h3 class="md-card__title">Преобразование приёма</h3>
            @include('zigbee::partials.rule', ['prefix' => 'incoming', 'rule' => $incoming])
        </div>
        <div class="md-card__body">
            <h3 class="md-card__title">Преобразование отправки</h3>
            @include('zigbee::partials.rule', ['prefix' => 'outgoing', 'rule' => $outgoing])
        </div>
        <div class="md-card__body">
            <div class="md-ui-actions">
                <x-md.button type="submit" variant="primary" icon="device-floppy">Сохранить привязку</x-md.button>
            </div>
        </div>
    </form>

    <div class="md-ui-form-grid">
        <section class="md-card">
            <header class="md-card__head">
                <div class="md-card__titles">
                    <div class="md-card__eyebrow">Проверка</div>
                    <h2 class="md-card__title">Преобразование без сохранения</h2>
                </div>
            </header>
            <div class="md-card__body">
                <form method="post" action="/module/zigbee/properties/{{ $propertyId }}/test">
                    <input type="hidden" name="_token" value="{{ $csrf }}">
                    <input type="hidden" name="direction" value="incoming">
                    <x-md.input name="test_value" label="Исходное значение" value=""
                                hint="Для приёма — как в сообщении; для отправки — значение свойства объекта." />
                    @include('zigbee::partials.rule', ['prefix' => 'incoming', 'rule' => $incoming])
                    <div class="md-ui-actions">
                        <x-md.button type="submit" variant="secondary" icon="flask">Проверить приём</x-md.button>
                    </div>
                </form>
                <form method="post" action="/module/zigbee/properties/{{ $propertyId }}/test">
                    <input type="hidden" name="_token" value="{{ $csrf }}">
                    <input type="hidden" name="direction" value="outgoing">
                    <x-md.input name="test_value" label="Значение свойства" value="" />
                    @include('zigbee::partials.rule', ['prefix' => 'outgoing', 'rule' => $outgoing])
                    <div class="md-ui-actions">
                        <x-md.button type="submit" variant="secondary" icon="flask">Проверить отправку</x-md.button>
                    </div>
                </form>
            </div>
        </section>

        <section class="md-card">
            <header class="md-card__head">
                <div class="md-card__titles">
                    <div class="md-card__eyebrow">Управление</div>
                    <h2 class="md-card__title">Ручная команда</h2>
                    <p class="md-card__subtitle">Отправка не подменяет подтверждение устройства: значение обновится его сообщением.</p>
                </div>
            </header>
            <div class="md-card__body">
                @if (!$canWrite)
                    <x-md.hint tone="warning" title="Устройство не разрешает запись"
                               text="exposes этого свойства не содержит права записи. Привязка только принимает значения." />
                @endif
                <form method="post" action="/module/zigbee/properties/{{ $propertyId }}/write">
                    <input type="hidden" name="_token" value="{{ $csrf }}">
                    <x-md.input name="value" label="Значение" value="" :disabled="!$canWrite"
                                hint="Сначала применяется правило отправки, затем проверяются диапазоны exposes." />
                    <div class="md-ui-actions">
                        <x-md.button type="submit" variant="primary" icon="send" :disabled="!$canWrite">Отправить</x-md.button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <section class="md-card">
        <header class="md-card__head">
            <div class="md-card__titles">
                <div class="md-card__eyebrow">Описание устройства</div>
                <h2 class="md-card__title">exposes</h2>
                <p class="md-card__subtitle">Данные Zigbee2MQTT: тип, доступ, диапазоны и варианты значений.</p>
            </div>
        </header>
        <div class="md-card__body">
            @if ($expose === '')
                <p class="md-table__muted">Описание ещё не получено из bridge/devices.</p>
            @else
                <pre class="md-table__code">{{ $expose }}</pre>
            @endif
        </div>
    </section>
</div>
