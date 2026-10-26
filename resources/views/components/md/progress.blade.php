@props([
    'value' => 0,
    'max' => 100,
    'tone' => 'primary',
    'label' => null,
    'showValue' => false,
    'size' => 'md',
])

@php
    $max = max(1, (float)$max);
    $value = max(0, min($max, (float)$value));
    $percent = round($value / $max * 100, 1);
    $tones = ['primary', 'success', 'warning', 'danger', 'info', 'neutral'];
    $tone = in_array($tone, $tones, true) ? $tone : 'primary';
@endphp

<div class="md-progress-row">
    @if ($label !== null || $showValue)
        <div class="md-progress-row__head">
            <span class="md-progress-row__label">{{ $label }}</span>
            @if ($showValue)<span class="md-progress-row__value">{{ $percent }}%</span>@endif
        </div>
    @endif
    <div {{ $attributes->class(['md-progress', 'md-progress--' . $tone, 'md-progress--sm' => $size === 'sm']) }}
         role="progressbar"
         aria-valuenow="{{ $value }}"
         aria-valuemin="0"
         aria-valuemax="{{ $max }}"
         aria-label="{{ $label ?? 'Прогресс' }}">
        <span class="md-progress__bar" style="width:{{ $percent }}%"></span>
    </div>
</div>
