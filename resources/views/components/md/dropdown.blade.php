@props([
    'label' => 'Действия',
    'variant' => 'secondary',
    'size' => 'md',
    'icon' => null,
    'split' => false,
    'align' => 'left',
    'actionHref' => null,
    'actionType' => 'button',
])

@php
    $btnClasses = array_filter([
        'md-btn',
        'md-btn--' . $variant,
        $size !== 'md' ? 'md-btn--' . $size : null,
    ]);
@endphp

<div {{ $attributes->class(['md-kit-dropdown', $split ? 'md-kit-dropdown--split' : null]) }}
     x-data="{ open: false }"
     @keydown.escape.stop="open = false; ($refs.trigger || $refs.splitToggle).focus()"
     @click.outside="open = false">
    @if ($split)
        <div class="md-btn-group" role="group">
            @if ($actionHref)
                <a href="{{ $actionHref }}" class="{{ implode(' ', $btnClasses) }}">
                    @if ($icon)<x-md.icon :name="$icon" class="md-btn__icon" />@endif
                    <span class="md-btn__label">{{ $label }}</span>
                </a>
            @else
                <button type="{{ $actionType }}" class="{{ implode(' ', $btnClasses) }}">
                    @if ($icon)<x-md.icon :name="$icon" class="md-btn__icon" />@endif
                    <span class="md-btn__label">{{ $label }}</span>
                </button>
            @endif
            <button x-ref="splitToggle"
                    type="button"
                    class="{{ implode(' ', $btnClasses) }} md-btn--dropdown-toggle"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-label="{{ $label }}: меню"
                    aria-haspopup="true">
                <x-md.icon name="chevron-down" />
            </button>
        </div>
    @else
        <button x-ref="trigger"
                type="button"
                class="{{ implode(' ', $btnClasses) }}"
                @click="open = !open"
                :aria-expanded="open.toString()"
                aria-haspopup="true">
            @if ($icon)<x-md.icon :name="$icon" class="md-btn__icon" />@endif
            <span class="md-btn__label">{{ $label }}</span>
            <x-md.icon name="chevron-down" class="md-btn__icon md-btn__icon--after" />
        </button>
    @endif

    <div class="md-kit-dropdown__panel {{ $align === 'right' ? 'md-kit-dropdown__panel--right' : '' }}"
         x-cloak
         x-show="open"
         x-transition.opacity
         @click="if ($event.target.closest('a, button')) open = false">
        {{ $slot }}
    </div>
</div>
