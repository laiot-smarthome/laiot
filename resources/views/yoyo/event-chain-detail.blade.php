<div class="md-ui-page md-ui-page--stack md-event-chain">
    <input type="hidden" name="search" value="{{ $search }}">
    <input type="hidden" name="type" value="{{ $type }}">
    <input type="hidden" name="status" value="{{ $status }}">
    <div class="md-events-detail-heading">
        <a class="md-events-back" href="/events?{{ http_build_query($context) }}" yoyo:get="back"><x-md.icon name="arrow-left" /> К списку цепочек</a>
        <div class="md-ui-actions">
            @if ($detail !== null)
                <span class="md-table__muted">Шагов: {{ $detail['chain']['count'] }}</span>
                <x-md.badge :tone="$chainTones[$detail['chain']['status']]">{{ $chainLabels[$detail['chain']['status']] }}</x-md.badge>
            @endif
            <x-md.button variant="secondary" size="sm" icon="refresh" yoyo:get="render" yoyo:spinning.attr="disabled">Обновить</x-md.button>
        </div>
    </div>
    @if ($detail === null)
        <x-md.empty-state icon="route" title="Цепочка не найдена" text="Записи могли быть удалены из журнала. Вернитесь к списку цепочек." />
    @else
        @if ($detail['chain']['partial'])
            <x-md.alert type="warning" text="Начало этой цепочки не сохранилось в журнале. Показаны все доступные действия от первой сохранившейся записи." />
        @endif
        @if ($detail['chain']['pending'] > 0)
            <x-md.alert type="info" :text="'В очереди остаются ожидающие или выполняющиеся действия: ' . $detail['chain']['pending'] . '. Обновите цепочку, чтобы увидеть новые шаги.'" />
        @endif
        <x-md.call-flow :title="'Цепочка #' . $selectedId"
                        :nodes="$detail['nodes']"
                        :started-at="\LaIOT\Services\Events\EventChainService::formatTime($detail['chain']['root']['created_at'])"
                        :max-depth="128" />
    @endif
</div>
