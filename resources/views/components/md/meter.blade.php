@props(['label', 'value' => 0, 'max' => 100, 'unit' => '', 'tone' => 'primary'])

<div {{ $attributes->class(['md-kit-meter']) }}>
    <x-md.progress :label="$label" :value="$value" :max="$max" :tone="$tone" />
    <span class="md-kit-meter__value">{{ $value }}{{ $unit }} / {{ $max }}{{ $unit }}</span>
</div>
