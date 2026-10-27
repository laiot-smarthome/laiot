@props(['label' => 'Действия'])

<div {{ $attributes->class(['md-kit-dropdown']) }} x-data="{ open: false }" @keydown.escape.stop="open = false; $refs.trigger.focus()" @click.outside="open = false">
    <button x-ref="trigger" type="button" class="md-btn md-btn--secondary" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true">
        <span class="md-btn__label">{{ $label }}</span><x-md.icon name="chevron-down" />
    </button>
    <div class="md-kit-dropdown__panel" x-cloak x-show="open" x-transition.opacity @click="if ($event.target.closest('a, button')) open = false">
        {{ $slot }}
    </div>
</div>
