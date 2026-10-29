@props([
    'label' => 'Действия',
    'variant' => 'secondary',
    'size' => 'md',
    'icon' => null,
    'iconOnly' => false,
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
     x-data="mdDropdown(@js($align))"
     @focusout="$nextTick(() => { if (open && !$el.contains(document.activeElement)) close() })"
     @keydown.escape.stop="close(true)">
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
                    @click="toggle()" @keydown.arrow-down.prevent="show(true)"
                    :aria-expanded="open.toString()"
                    aria-label="{{ $label }}: меню"
                    aria-haspopup="true">
                <x-md.icon name="chevron-down" />
            </button>
        </div>
    @else
        <button x-ref="trigger"
                type="button"
                class="{{ $iconOnly ? 'md-icon-btn' . ($size === 'sm' ? ' md-icon-btn--sm' : '') : implode(' ', $btnClasses) }}"
                @click="toggle()" @keydown.arrow-down.prevent="show(true)"
                @if ($iconOnly) aria-label="{{ $label }}" @endif
                :aria-expanded="open.toString()"
                aria-haspopup="true">
            @if ($icon)<x-md.icon :name="$icon" class="md-btn__icon" />@endif
            @if (!$iconOnly)
                <span class="md-btn__label">{{ $label }}</span>
                <x-md.icon name="chevron-down" class="md-btn__icon md-btn__icon--after" />
            @endif
        </button>
    @endif

    <div class="md-kit-dropdown__panel" popover="auto" x-ref="panel" x-cloak
         @toggle="open = $event.newState === 'open'"
         @keydown="move($event)"
         @click="if ($event.target.closest('a, button')) close()">
        {{ $slot }}
    </div>
</div>
