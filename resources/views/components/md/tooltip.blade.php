@props(['text', 'id' => null])

@php($id = $id ?? 'md-tooltip-' . bin2hex(random_bytes(6)))
<span {{ $attributes->class(['md-kit-tooltip']) }} x-data="{ open: false }" x-init="const trigger = $el.querySelector('button, a, input, select, textarea, [tabindex]'); if (trigger) trigger.setAttribute('aria-describedby', [trigger.getAttribute('aria-describedby'), @js($id)].filter(Boolean).join(' '))" @keydown.escape.stop="open = false" @mouseenter="open = true" @mouseleave="open = false" @focusin="open = true" @focusout="open = false">
    {{ $slot }}
    <span id="{{ $id }}" role="tooltip" class="md-kit-tooltip__bubble" x-cloak x-show="open" x-transition.opacity>{{ $text }}</span>
</span>
