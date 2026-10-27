<div class="md-kit-toasts" x-data aria-live="polite" aria-atomic="false">
    <template x-for="toast in $store.mdToasts.items" :key="toast.id">
        <div class="md-kit-toast" :class="'md-kit-toast--' + toast.tone" role="status">
            <div><strong x-text="toast.title"></strong><p x-show="toast.text" x-text="toast.text"></p></div>
            <button type="button" class="md-icon-btn" aria-label="Закрыть уведомление" @click="$store.mdToasts.remove(toast.id)"><x-md.icon name="x" /></button>
        </div>
    </template>
</div>
