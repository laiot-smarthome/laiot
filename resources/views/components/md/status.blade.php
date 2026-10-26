@props([
    'state' => 'idle',
    'label' => null,
    'pulse' => false,
])

@php
    $map = [
        'online' => 'Онлайн',
        'offline' => 'Офлайн',
        'error' => 'Ошибка',
        'warning' => 'Внимание',
        'idle' => 'Неактивно',
    ];
    $state = array_key_exists($state, $map) ? $state : 'idle';
    $label = $label ?? $map[$state];
@endphp

<span {{ $attributes->class(['md-status', 'md-status--' . $state, 'is-pulse' => $pulse]) }}>
    <span class="md-status__dot" aria-hidden="true"></span>
    <span class="md-status__label">{{ $label }}</span>
</span>
