<div class="md-toast-stack" x-data aria-label="Уведомления об операциях" popover="manual"
     x-effect="if ($el.showPopover) { if ($store.mdToasts.items.length) { $el.hidePopover(); $el.showPopover(); } else $el.hidePopover(); }">
    <template x-for="toast in $store.mdToasts.items" :key="toast.id">
        <div class="md-toast" :class="'md-toast--' + toast.tone" :role="toast.tone === 'danger' ? 'alert' : 'status'"
             @mouseenter="$store.mdToasts.pause(toast.id)" @mouseleave="$store.mdToasts.resume(toast.id)"
             @focusin="$store.mdToasts.pause(toast.id)" @focusout="if (!$el.contains($event.relatedTarget)) $store.mdToasts.resume(toast.id)"
             x-transition.opacity>
            <span class="md-toast__icon"><i class="ti" :class="{info: 'ti-info-circle', success: 'ti-circle-check', warning: 'ti-alert-triangle', danger: 'ti-circle-x'}[toast.tone]" aria-hidden="true"></i></span>
            <div class="md-toast__body"><div class="md-toast__title" x-text="toast.title"></div><div class="md-toast__text" x-show="toast.text" x-text="toast.text"></div></div>
            <button type="button" class="md-toast__close" aria-label="Закрыть уведомление" @click="$store.mdToasts.remove(toast.id)"><x-md.icon name="x" /></button>
        </div>
    </template>
</div>
