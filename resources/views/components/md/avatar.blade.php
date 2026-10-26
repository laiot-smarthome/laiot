@props([
    'name' => '',
    'src' => null,
    'size' => 'md',
    'tone' => 'neutral',
])

@php
    $size = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
    $tone = in_array($tone, ['neutral', 'primary', 'success', 'warning', 'danger', 'info'], true) ? $tone : 'neutral';
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim((string)$name)) ?: [], 0, 2) as $part) {
        if ($part !== '') {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
        }
    }
    $initials = $initials !== '' ? $initials : '?';
@endphp

<span {{ $attributes->class(['md-avatar', 'md-avatar--' . $size, 'md-avatar--' . $tone]) }} title="{{ $name }}">
    @if ($src !== null)
        <img src="{{ $src }}" alt="{{ $name }}" loading="lazy">
    @else
        {{ $initials }}
    @endif
</span>
