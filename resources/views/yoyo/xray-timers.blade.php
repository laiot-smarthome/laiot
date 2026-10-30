@if ($timers === [])
    <x-md.card><x-md.empty-state icon="clock" :title="$search === '' ? 'Активных таймеров нет' : 'Таймеры не найдены'" text="Отложенные действия из setTimeOut() появятся здесь со временем запуска и обратным отсчётом." /></x-md.card>
@else
    <div class="md-xray-card-grid">
        @foreach ($timers as $timer)
            <x-md.card class="md-xray-timer" :title="$timer['title']" :subtitle="'Таймер #' . $timer['id']">
                <div class="md-xray-timer__countdown"><x-md.icon name="hourglass" /><strong x-data="mdXrayCountdown({{ (strtotime($timer['run_at']) ?: 0) * 1000 }})" x-text="label">{{ $timer['run_at'] }}</strong><x-md.badge :tone="!$timer['cancelable'] ? 'info' : 'neutral'">{{ !$timer['cancelable'] ? 'Выполняется' : 'Запланирован' }}</x-md.badge></div>
                <dl class="md-xray-facts"><div><dt>Запуск</dt><dd>{{ $timer['run_at'] }}</dd></div><div><dt>Создан</dt><dd>{{ $timer['created_at'] }}</dd></div></dl>
                <details class="md-xray-preview" data-xray-expand><summary><x-md.icon name="code" /> Код таймера</summary><pre>{{ $timer['code'] }}</pre></details>
                <div class="md-xray-card-actions" x-data="{confirming: false}" x-bind:data-xray-confirming="confirming ? '' : null">
                    <x-md.button variant="secondary" size="sm" icon="player-stop" x-on:click="confirming = true" x-show="!confirming" :disabled="!$timer['cancelable']">Отменить таймер</x-md.button>
                    <span x-show="confirming" x-cloak>Отменить этот таймер?</span>
                    <x-md.button variant="danger" size="sm" x-show="confirming" x-cloak yoyo:post="cancelTimer({{ $timer['id'] }})">Подтвердить</x-md.button>
                    <x-md.button variant="ghost" size="sm" x-show="confirming" x-cloak x-on:click="confirming = false">Назад</x-md.button>
                </div>
            </x-md.card>
        @endforeach
    </div>
    <p class="md-table__muted">Показаны ближайшие {{ count($timers) }} таймеров. Выполняющийся код отменить нельзя.</p>
@endif
