@props([
    'icon',
    'label',
    'href' => null,
    'variant' => null,
    'size' => 'md',
    'type' => 'button',
    'disabled' => false,
])

@php
    $variants = ['primary', 'secondary', 'danger', 'outline'];
    $classes = array_filter([
        'md-icon-btn',
        $variant !== null && in_array($variant, $variants, true) ? 'md-icon-btn--' . $variant : null,
        $size === 'sm' ? 'md-icon-btn--sm' : null,
        $size === 'lg' ? 'md-icon-btn--lg' : null,
    ]);
@endphp

@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}
       title="{{ $label }}" aria-label="{{ $label }}">
        <x-md.icon :name="$icon" />
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}
            title="{{ $label }}" aria-label="{{ $label }}" @disabled($disabled)>
        <x-md.icon :name="$icon" />
    </button>
@endif
