<!-- 2026-10-29 — общая разметка Notify работает также без компилятора Blade. -->
<div class="md-toast-stack" x-data aria-label="Уведомления об операциях" popover="manual"
     x-effect="if ($el.showPopover) { if ($store.mdToasts.items.length) { $el.hidePopover(); $el.showPopover(); } else $el.hidePopover(); }">
    <template x-for="toast in $store.mdToasts.items" :key="toast.id">
        <div class="md-toast" :class="'md-toast--' + toast.tone" :role="toast.tone === 'danger' ? 'alert' : 'status'"
             x-data="{shown: false}" x-init="$nextTick(() => shown = true)" x-show="shown && !toast.closing" x-cloak
             @mouseenter="$store.mdToasts.pause(toast.id, 'hover')" @mouseleave="$store.mdToasts.resume(toast.id, 'hover')"
             @focusin="$store.mdToasts.pause(toast.id, 'focus')" @focusout="if (!$el.contains($event.relatedTarget)) $store.mdToasts.resume(toast.id, 'focus')"
             x-transition:enter="md-toast-enter" x-transition:enter-start="md-toast-hidden" x-transition:enter-end="md-toast-visible"
             x-transition:leave="md-toast-leave" x-transition:leave-start="md-toast-visible" x-transition:leave-end="md-toast-hidden">
            <span class="md-toast__icon"><i class="ti" :class="{info: 'ti-info-circle', success: 'ti-circle-check', warning: 'ti-alert-triangle', danger: 'ti-circle-x'}[toast.tone]" aria-hidden="true"></i></span>
            <div class="md-toast__body"><div class="md-toast__title" x-text="toast.title"></div><div class="md-toast__text" x-show="toast.text" x-text="toast.text"></div></div>
            <button type="button" class="md-toast__close" aria-label="Закрыть уведомление" @click="$store.mdToasts.remove(toast.id)"><i class="ti ti-x" aria-hidden="true"></i></button>
            <span class="md-toast__progress" x-show="toast.duration > 0" aria-hidden="true"><span :style="{animationDuration: toast.duration + 'ms', animationPlayState: toast.pauses.length ? 'paused' : 'running'}"></span></span>
        </div>
    </template>
</div>
