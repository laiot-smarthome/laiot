@props([
    'label' => null,
    'value' => '',
    'unit' => null,
    'icon' => null,
    'tone' => 'primary',
    'meta' => null,
    'metaHtml' => null,
    'delta' => null,
    'deltaTone' => 'neutral',
    'href' => null,
    'valueHtml' => null,
])

@php
    $tones = ['primary', 'success', 'warning', 'danger', 'info', 'neutral'];
    $deltaTones = ['success', 'danger', 'neutral'];
    $tone = in_array($tone, $tones, true) ? $tone : 'primary';
    $deltaTone = in_array($deltaTone, $deltaTones, true) ? $deltaTone : 'neutral';
    $tag = $href !== null ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href !== null) href="{{ $href }}" @endif {{ $attributes->class(['md-kpi', 'md-kpi--' . $tone]) }}>
    <div class="md-kpi__top">
        @if ($icon !== null)<span class="md-kpi__icon"><x-md.icon :name="$icon" /></span>@endif
        <span class="md-kpi__label">{{ $label }}</span>
        @if ($delta !== null)
            <span class="md-kpi__delta md-kpi__delta--{{ $deltaTone }}">{{ $delta }}</span>
        @endif
    </div>
    <div class="md-kpi__value">
        @if ($valueHtml !== null){!! $valueHtml !!}@else{{ $value }}@endif
        @if ($unit !== null)<small>{{ $unit }}</small>@endif
    </div>
    @if ($meta !== null || $metaHtml !== null)
        <div class="md-kpi__meta">@if ($metaHtml !== null){!! $metaHtml !!}@else{{ $meta }}@endif</div>
    @endif
</{{ $tag }}>
