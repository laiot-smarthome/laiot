@props(['label', 'value', 'unit' => '', 'icon' => 'activity', 'tone' => 'primary', 'trend' => null, 'hint' => null])

@php($tone = in_array($tone, ['primary', 'success', 'warning', 'danger', 'info', 'neutral'], true) ? $tone : 'primary')
<article {{ $attributes->class(['md-sensor', 'md-sensor--' . $tone]) }}>
    <header class="md-sensor__head"><span class="md-sensor__icon"><x-md.icon :name="$icon" /></span><span class="md-sensor__label">{{ $label }}</span>@if ($trend !== null)<span class="md-sensor__trend">{{ $trend }}</span>@endif</header>
    <div class="md-sensor__value">{{ $value }}<small>{{ $unit }}</small></div>
    @if ($hint !== null)<div class="md-sensor__hint">{{ $hint }}</div>@endif
</article>
