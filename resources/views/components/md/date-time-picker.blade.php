@props(['type' => 'date', 'name', 'label', 'value' => '', 'id' => null, 'disabled' => false, 'required' => false])
@php
    $type = $type === 'time' ? 'time' : 'date';
    $id = $id ?? 'md-picker-' . substr(md5($name), 0, 8);
@endphp
<x-md.field :label="$label" :for="$id" :required="$required">
    <div class="md-date-picker" x-data="mdDateTimePicker(@js($type), @js((string) $value))" @click.outside="open = false" @keydown.escape.stop="open = false; $refs.trigger.focus()">
        <div class="md-date-picker__control">
            <input class="md-input" type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" x-model="value" @change="sync()" @disabled($disabled) @required($required) {{ $attributes }}>
            <button type="button" class="md-icon-btn md-icon-btn--sm md-date-picker__trigger" x-cloak x-ref="trigger" @click="toggle()" :aria-expanded="open.toString()" aria-label="{{ $type === 'date' ? 'Открыть календарь' : 'Выбрать время' }}" aria-controls="{{ $id }}-popup" @disabled($disabled)><x-md.icon :name="$type === 'date' ? 'calendar' : 'clock'" /></button>
        </div>
        <div class="md-date-picker__popup" id="{{ $id }}-popup" x-cloak x-show="open" aria-label="{{ $type === 'date' ? 'Календарь' : 'Выбор времени' }}">
            @if ($type === 'date')
                <div class="md-date-picker__header"><button type="button" class="md-icon-btn md-icon-btn--sm" @click="move(-1)" aria-label="Предыдущий месяц"><x-md.icon name="chevron-left" /></button><strong x-text="monthLabel"></strong><button type="button" class="md-icon-btn md-icon-btn--sm" @click="move(1)" aria-label="Следующий месяц"><x-md.icon name="chevron-right" /></button></div>
                <div class="md-date-picker__grid" aria-label="Дни недели"><template x-for="day in weekdays" :key="day"><span class="md-date-picker__weekday" x-text="day"></span></template><template x-for="(day, index) in days" :key="index"><span><button x-show="day" type="button" class="md-date-picker__day" :class="{'is-selected': day && day.value === value, 'is-today': day && day.value === today}" :aria-label="day ? day.label : ''" :aria-pressed="day && day.value === value ? 'true' : 'false'" @click="selectDate(day.value)" x-text="day ? day.number : ''"></button></span></template></div>
                <button type="button" class="md-btn md-btn--secondary md-btn--sm md-date-picker__today" @click="selectDate(today)">Сегодня</button>
            @else
                <div class="md-date-picker__time"><label>Часы<select class="md-select" x-model="hour" @change="selectTime()"><template x-for="item in hours" :key="item"><option :value="item" x-text="item"></option></template></select></label><span aria-hidden="true">:</span><label>Минуты<select class="md-select" x-model="minute" @change="selectTime()"><template x-for="item in minutes" :key="item"><option :value="item" x-text="item"></option></template></select></label></div>
                <button type="button" class="md-btn md-btn--primary md-btn--sm" @click="selectTime(); open = false; $refs.trigger.focus()">Готово</button>
            @endif
        </div>
    </div>
</x-md.field>
