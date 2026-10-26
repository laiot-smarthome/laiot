@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconAfter' => null,
    'href' => null,
    'type' => 'button',
    'disabled' => false,
    'block' => false,
])

@php
    $variants = ['primary', 'secondary', 'outline', 'ghost', 'danger', 'success'];
    $sizes = ['sm', 'md', 'lg'];
    $variant = in_array($variant, $variants, true) ? $variant : 'primary';
    $size = in_array($size, $sizes, true) ? $size : 'md';
    $classes = array_filter([
        'md-btn',
        'md-btn--' . $variant,
        $size !== 'md' ? 'md-btn--' . $size : null,
        $block ? 'md-btn--block' : null,
        $disabled ? 'is-disabled' : null,
    ]);
@endphp

@if ($href)
    <a href="{{ $disabled ? '#' : $href }}"
       role="button"
       @if ($disabled) aria-disabled="true" tabindex="-1" @endif
       {{ $attributes->class($classes) }}>
        @if ($icon)<x-md.icon :name="$icon" class="md-btn__icon" />@endif
        <span class="md-btn__label">{{ $slot }}</span>
        @if ($iconAfter)<x-md.icon :name="$iconAfter" class="md-btn__icon md-btn__icon--after" />@endif
    </a>
@else
    <button type="{{ $type }}"
            @disabled($disabled)
            {{ $attributes->class($classes) }}>
        @if ($icon)<x-md.icon :name="$icon" class="md-btn__icon" />@endif
        <span class="md-btn__label">{{ $slot }}</span>
        @if ($iconAfter)<x-md.icon :name="$iconAfter" class="md-btn__icon md-btn__icon--after" />@endif
    </button>
@endif
