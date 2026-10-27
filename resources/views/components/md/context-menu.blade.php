@props(['label' => 'Контекстное меню'])
<div {{ $attributes->class(['md-kit-context']) }} x-data="{open: false, left: 0, top: 0, show(event) { const box = this.$el.getBoundingClientRect(); this.left = Math.min(event.clientX || box.left, window.innerWidth - 200); this.top = Math.min(event.clientY || box.bottom, window.innerHeight - 160); this.open = true; this.$nextTick(() => this.$refs.menu.querySelector('button, a')?.focus()); }}" @contextmenu.prevent="show($event)" @keydown.shift.f10.prevent="show($event)" @keydown.escape.stop="open = false" @click.outside="open = false">
    <div tabindex="0" aria-label="{{ $label }}">{{ $trigger }}</div>
    <div x-ref="menu" class="md-kit-dropdown__panel md-kit-context__panel" x-cloak x-show="open" :style="{left: left + 'px', top: top + 'px'}" @click="if ($event.target.closest('a, button')) open = false">{{ $slot }}</div>
</div>
