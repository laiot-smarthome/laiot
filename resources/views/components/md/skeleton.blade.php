@props([
    'variant' => 'text',
    'width' => null,
    'height' => null,
    'lines' => 1,
])

@php
    $variant = in_array($variant, ['text', 'circle', 'block'], true) ? $variant : 'text';
    $lines = max(1, (int)$lines);
    $style = [];
    if ($width !== null) {
        $style[] = '--md-skeleton-width:' . $width;
    }
    if ($height !== null) {
        $style[] = '--md-skeleton-height:' . $height;
    }
@endphp

@if ($variant === 'text' && $lines > 1)
    <div {{ $attributes->class(['md-skeleton-stack']) }} aria-hidden="true">
        @for ($i = 1; $i <= $lines; $i++)
            <span @class(['md-skeleton', 'md-skeleton--text', 'md-skeleton--short' => $i === $lines])></span>
        @endfor
    </div>
@else
    <span {{ $attributes->class(['md-skeleton', 'md-skeleton--' . $variant]) }}
          @if ($style !== []) style="{{ implode(';', $style) }}" @endif
          aria-hidden="true"></span>
@endif
