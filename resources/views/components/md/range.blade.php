@props(['name' => null, 'id' => null, 'value' => 50, 'min' => 0, 'max' => 100, 'step' => 1, 'label' => null, 'unit' => '', 'hint' => null, 'disabled' => false])

@php($id = $id ?? 'md-range-' . substr(md5(($name ?? '') . '|' . ($label ?? '')), 0, 8))
<div class="md-range-field" x-data="{ value: @js((string) $value) }">
    <div class="md-range-field__head">
        @if ($label !== null)<label class="md-range-field__label" for="{{ $id }}">{{ $label }}</label>@endif
        <output class="md-range-field__value" for="{{ $id }}" x-text="value + @js($unit)">{{ $value }}{{ $unit }}</output>
    </div>
    <input type="range" id="{{ $id }}" @if ($name !== null) name="{{ $name }}" @endif
           min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ $value }}"
           @disabled($disabled) @if ($hint !== null) aria-describedby="{{ $id }}-help" @endif
           x-model="value" :style="{'--md-range-progress': ((Number(value) - Number(@js($min))) / (Number(@js($max)) - Number(@js($min)) || 1) * 100) + '%'}"
           {{ $attributes->class(['md-range']) }}>
    <div class="md-range-field__scale" aria-hidden="true"><span>{{ $min }}{{ $unit }}</span><span>{{ $max }}{{ $unit }}</span></div>
    @if ($hint !== null)<div id="{{ $id }}-help" class="md-field__hint">{{ $hint }}</div>@endif
</div>
