@props(['tone' => 'neutral'])

@php
    $tones = ['neutral', 'info', 'success', 'warning', 'danger'];
    $tone = in_array($tone, $tones, true) ? $tone : 'neutral';
@endphp

<span {{ $attributes->class(['md-badge', 'md-badge--' . $tone]) }}>{{ $slot }}</span>
