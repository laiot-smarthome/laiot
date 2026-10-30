<x-md.table label="Циклы и сервисы" class="md-xray-process-table">
    <x-slot:head><th scope="col">Процесс</th><th scope="col">Состояние</th><th scope="col">PID</th><th scope="col">Heartbeat</th><th scope="col">Управление</th></x-slot:head>
    <x-slot:body>
    @foreach ($cycles as $cycle)
        @if ($search === '' || mb_stripos($cycle['id'] . ' ' . $cycle['task'], $search) !== false)
            <tr>
                <td><strong>{{ $cycle['id'] }}</strong><div class="md-table__muted">{{ $cycle['kind'] === 'core' ? 'Ядро' : 'Модуль' }}{{ $cycle['task'] !== $cycle['id'] ? ' · ' . $cycle['task'] : '' }}</div>
                    @if ($cycle['error'] !== '')<details class="md-xray-error" data-xray-expand><summary>Ошибка</summary><pre>{{ $cycle['error'] }}</pre></details>@endif
                </td>
                <td><x-md.badge :tone="$cycle['tone']">{{ $cycle['status'] }}</x-md.badge></td>
                <td><code>{{ $cycle['pid'] ?: '—' }}</code></td>
                <td><span class="md-table__muted">{{ $cycle['updated_at'] !== '' ? \LaIOT\Services\Events\EventChainService::formatTime($cycle['updated_at']) : 'Нет отметок' }}</span></td>
                <td><div class="md-ui-actions" x-data="{confirming: ''}" x-bind:data-xray-confirming="confirming !== '' ? '' : null">
                    @foreach (['start' => ['Запустить', 'player-play'], 'restart' => ['Перезапустить', 'refresh'], 'stop' => ['Остановить', 'player-stop']] as $operation => $action)
                        @if (($operation === 'start') === ($cycle['pid'] === 0))
                        <x-md.icon-button variant="secondary" size="sm" :icon="$action[1]" :label="$action[0]" x-show="confirming === ''" x-on:click="confirming = '{{ $operation }}'" />
                        <span x-show="confirming === '{{ $operation }}'" x-cloak>{{ $action[0] }} процесс? <x-md.button variant="danger" size="sm" yoyo:post="control('{{ $cycle['kind'] }}', '{{ $cycle['id'] }}', '{{ $operation }}')">Подтвердить</x-md.button></span>
                        @endif
                    @endforeach
                    <x-md.button variant="ghost" size="sm" x-show="confirming !== ''" x-cloak x-on:click="confirming = ''">Назад</x-md.button>
                </div></td>
            </tr>
        @endif
    @endforeach
    </x-slot:body>
</x-md.table>
<p class="md-table__muted">Состояние по heartbeat. Команда worker управляет всеми задачами этого процесса.</p>
