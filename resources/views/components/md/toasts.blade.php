<div class="md-toast-stack" x-data aria-live="polite" aria-atomic="false">
    <template x-for="toast in $store.mdToasts.items" :key="toast.id">
        <div class="md-toast" :class="'md-toast--' + toast.tone" role="status">
            <span class="md-toast__icon"><i class="ti" :class="{info: 'ti-info-circle', success: 'ti-circle-check', warning: 'ti-alert-triangle', danger: 'ti-circle-x'}[toast.tone]" aria-hidden="true"></i></span>
            <div class="md-toast__body"><div class="md-toast__title" x-text="toast.title"></div><div class="md-toast__text" x-show="toast.text" x-text="toast.text"></div></div>
            <button type="button" class="md-toast__close" aria-label="Закрыть уведомление" @click="$store.mdToasts.remove(toast.id)"><x-md.icon name="x" /></button>
        </div>
    </template>
</div>
