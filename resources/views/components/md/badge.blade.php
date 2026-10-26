@props(['tone' => 'neutral', 'dot' => false])

@php
    $tones = ['neutral', 'primary', 'info', 'success', 'warning', 'danger', 'outline'];
    $tone = in_array($tone, $tones, true) ? $tone : 'neutral';
@endphp

<span {{ $attributes->class(['md-badge', 'md-badge--' . $tone]) }}>
    @if ($dot)<span class="md-badge__dot" aria-hidden="true"></span>@endif
    {{ $slot }}
</span>
