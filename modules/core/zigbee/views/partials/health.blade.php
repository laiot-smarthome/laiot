@php
    /** @var array<string, mixed> $settings */
    /** @var array<string, mixed> $defaults */
    $events = is_array($settings['events'] ?? null) ? $settings['events'] : [];
    $eventLabels = \LaIOT\Zigbee\HealthSettings::EVENTS;
@endphp

<div class="md-ui-form-stack">
    <x-md.switch name="enabled" label="Мониторинг включён" :checked="(bool)($settings['enabled'] ?? false)"
                 description="Проверять доступность, батареи и создавать инциденты." />

    <div class="md-ui-form-grid">
        <x-md.input type="number" name="offline_delay" label="Задержка «нет связи», секунды"
                    :value="(string)($settings['offline_delay'] ?? 0)"
                    hint="Сколько секунд устройство должно молчать, прежде чем считать его offline." />
        <x-md.input type="number" name="timeout_mains" label="Таймаут устройств от сети, минуты"
                    :value="(string)($settings['timeout_mains'] ?? 0)"
                    hint="0 — не проверять по времени." />
        <x-md.input type="number" name="timeout_battery" label="Таймаут батарейных устройств, минуты"
                    :value="(string)($settings['timeout_battery'] ?? 0)"
                    hint="0 — не проверять по времени." />
    </div>

    <div class="md-ui-form-grid">
        <x-md.input type="number" name="critical" label="Критический заряд, %" :value="(string)($settings['critical'] ?? 10)" />
        <x-md.input type="number" name="low" label="Низкий заряд, %" :value="(string)($settings['low'] ?? 20)" />
        <x-md.input type="number" name="recovery" label="Возврат в норму, %" :value="(string)($settings['recovery'] ?? 25)"
                    hint="критический &lt; низкий &lt; возврат в норму." />
    </div>

    <div class="md-ui-form-grid">
        <x-md.select name="battery_source" label="Источник заряда" :selected="(string)($settings['battery_source'] ?? 'auto')"
                     :options="['auto' => 'Готовый процент или напряжение', 'voltage' => 'Только напряжение', 'off' => 'Не контролировать']" />
        <x-md.select name="voltage_unit" label="Единица напряжения" :selected="(string)($settings['voltage_unit'] ?? 'mV')"
                     :options="['mV' => 'mV', 'V' => 'V']" />
        <x-md.select name="voltage_profile" label="Профиль батареи" :selected="(string)($settings['voltage_profile'] ?? 'none')"
                     :options="['none' => 'Без оценки', 'linear3v' => 'Линейный 2.1–3.0 В', 'alkaline' => 'Щелочная 0.9–1.5 В', 'custom' => 'Своя таблица']" />
    </div>

    <x-md.input name="voltage_path" label="Свойство напряжения" :value="(string)($settings['voltage_path'] ?? 'voltage')"
                hint="Путь свойства Zigbee, например voltage." />
    <x-md.textarea name="voltage_points" label="Своя таблица напряжения" :value="$points" rows="4"
                   hint="Одна точка на строку: вольты=процент, по возрастанию. Например 2.1=0 и 3.0=100." />

    <h3 class="md-card__title">События</h3>
    @foreach ($eventLabels as $event => $label)
        @php
            $rule = is_array($events[$event] ?? null) ? $events[$event] : ($defaults['events'][$event] ?? []);
        @endphp
        <section class="md-card">
            <header class="md-card__head">
                <div class="md-card__titles">
                    <div class="md-card__eyebrow">Событие</div>
                    <h4 class="md-card__title">{{ $label }}</h4>
                </div>
            </header>
            <div class="md-card__body">
                <div class="md-ui-form-grid">
                    <x-md.switch :name="'events[' . $event . '][enabled]'" label="Событие включено"
                                 :checked="(bool)($rule['enabled'] ?? false)" />
                    <x-md.switch :name="'events[' . $event . '][notify]'" label="Уведомление в панель"
                                 :checked="(bool)($rule['notify'] ?? false)" />
                </div>
                <div class="md-ui-form-grid">
                    <x-md.input type="number" :name="'events[' . $event . '][level]'" label="Уровень"
                                :value="(string)($rule['level'] ?? 2)" hint="0–10; 3 и выше — опасное." />
                    <x-md.input type="number" :name="'events[' . $event . '][repeat_hours]'" label="Повтор, часы"
                                :value="(string)($rule['repeat_hours'] ?? 0)" hint="0 — не повторять." />
                </div>
                <x-md.input :name="'events[' . $event . '][message]'" label="Текст уведомления"
                            :value="(string)($rule['message'] ?? '')"
                            hint="Подстановки: {device}, {room}, {value}, {event}." />
                <x-md.textarea :name="'events[' . $event . '][code]'" label="PHP-обработчик" rows="3"
                               :value="(string)($rule['code'] ?? '')"
                               hint="Необязательно. Код без открывающих тегов; получает $params." />
            </div>
        </section>
    @endforeach
</div>
