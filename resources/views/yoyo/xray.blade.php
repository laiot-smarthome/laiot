<div class="md-xray" yoyo:sync="this:replace" yoyo:spinning.attr="aria-busy" yoyo:spin-on="changeTab,filter,togglePause,nextPage,previousPage,open,back,cancelTimer,control">
    <div class="md-ui-kpi-grid md-xray-kpis">
        <a href="/xray?tab=property" class="md-xray-stat" yoyo:post="changeTab('property')" data-xray-label="Изменения свойств"><x-md.kpi label="Изменения свойств" :value="(string)($counts['property'] ?? 0)" icon="arrows-exchange" tone="primary" meta="За последние 24 часа" /></a>
        <a href="/xray?tab=debug" class="md-xray-stat" yoyo:post="changeTab('debug')" data-xray-label="Отладка"><x-md.kpi label="Отладочные записи" :value="(string)($counts['debug'] ?? 0)" icon="bug" tone="info" meta="debmes() и dump() · за сутки" /></a>
        <a href="/xray?tab=timers" class="md-xray-stat" yoyo:post="changeTab('timers')" data-xray-label="Таймеры"><x-md.kpi label="Активные таймеры" :value="(string)$summary['timers']" icon="clock" :tone="$summary['due'] > 0 ? 'warning' : 'primary'" :meta="'Ожидают выполнения: ' . $summary['due']" /></a>
        <a href="/xray?tab=cycles" class="md-xray-stat" yoyo:post="changeTab('cycles')" data-xray-label="Фоновые процессы"><x-md.kpi label="Фоновые процессы" :value="$summary['running'] . ' / ' . $summary['cycles']" icon="activity-heartbeat" :tone="$summary['running'] > 0 ? 'success' : 'neutral'" meta="Работают / зарегистрированы" /></a>
    </div>
    <nav class="md-tabs md-xray-tabs" aria-label="Разделы X-Ray">
        @foreach ($tabs as $key => $label)
            <a href="/xray?tab={{ $key }}" class="nav-link {{ $tab === $key ? 'active' : '' }}"
                    data-xray-label="{{ $label }}" yoyo:post="changeTab('{{ $key }}')" @if ($tab === $key) aria-current="page" @endif><x-md.icon :name="match($key) {'property' => 'arrows-exchange', 'debug' => 'bug', 'script' => 'file-code', 'event' => 'bolt', 'timers' => 'clock', 'cycles' => 'activity-heartbeat', default => 'list-details'}" /><span>{{ $label }}</span></a>
        @endforeach
    </nav>

    @if (!$paused && $page === 1 && $selectedId === 0)
        <button type="button" hidden data-xray-poll yoyo:sync="closest .md-xray:drop" yoyo:post="refresh" yoyo:on="every 2s [!document.hidden &amp;&amp; !document.querySelector('[data-xray-confirming], [data-xray-expand][open], [data-md-inspect][open]')]" aria-label="Обновить X-Ray"></button>
    @endif

    <div class="md-xray-workspace">
        <div class="md-xray-loading" yoyo:spinning.class="is-active" yoyo:spin-on="changeTab,filter,togglePause,nextPage,previousPage,open,back,cancelTimer,control" role="status" aria-live="polite">
            <x-md.mascot pose="work" class="md-xray-loading__mascot" />
            <strong>Загружаем данные…</strong>
            <span>Собираем данные диагностики</span>
            <x-md.loader size="sm" label="Загружаем данные диагностики" />
        </div>
        <div class="md-xray-content" yoyo:spinning.class="is-loading" yoyo:spin-on="changeTab,filter,togglePause,nextPage,previousPage,open,back,cancelTimer,control">
    @if ($selectedId > 0)
        <div class="md-ui-actions">
            <x-md.button variant="secondary" icon="arrow-left" yoyo:post="back">К ленте</x-md.button>
        </div>
        @if ($detail === null)
            <x-md.empty-state icon="search" title="Запись недоступна" text="Срок хранения записи истёк или она удалена." />
        @else
            <x-md.card :title="$detail['name']" :subtitle="$detail['created_at'] . ' · ' . $detail['source']">
                @if ($detail['kind'] === 'script')
                    <div class="md-ui-actions">
                        <x-md.badge :tone="$detail['payload']['ok'] ? 'success' : 'danger'">{{ $detail['payload']['ok'] ? 'Выполнен' : 'Ошибка' }}</x-md.badge>
                        <span>{{ $detail['payload']['duration_ms'] }} мс</span>
                    </div>
                    @if ($detail['payload']['error'] !== '')<pre class="md-xray-error">{{ $detail['payload']['error'] }}</pre>@endif
                    @if ($detail['payload']['output'] !== '')<h3>Вывод</h3><pre>{{ $detail['payload']['output'] }}</pre>@endif
                    <h3>Результат</h3>
                    <x-md.data-view :snapshot="$detail['payload']['result']" />
                @elseif (isset($detail['payload']['value']))
                    <x-md.data-view :snapshot="$detail['payload']['value']" />
                @else
                    <pre>{{ json_encode($detail['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                @endif
            </x-md.card>
        @endif
    @else
        <div class="md-xray-section-head"><div>
            <h2>{{ match($tab) {'property' => 'Изменения свойств', 'debug' => 'Отладка кода', 'script' => 'Запуски сценариев', 'event' => 'Системные события', 'timers' => 'Таймеры', 'cycles' => 'Циклы и сервисы', default => 'Лента активности'} }}</h2>
            <p>{{ match($tab) {'debug' => 'Типы, значения и вложенная структура из debmes() и dump().', 'timers' => 'Отложенные действия и время до их выполнения.', 'cycles' => 'Состояние процессов и управление их жизненным циклом.', 'event' => 'Поступающие события и зарегистрированные подписчики.', 'script' => 'Результат, длительность и ошибки запусков.', default => 'Изменения свойств и автоматизация за последние трое суток.'} }}</p>
        </div><span class="md-xray-live {{ $paused ? 'is-paused' : '' }}"><span aria-hidden="true"></span>{{ $paused ? 'На паузе' : 'В реальном времени' }}</span></div>
        <div class="md-xray-toolbar">
            <x-md.input type="search" name="search" id="xray-search" :value="$search" autocomplete="off"
                        placeholder="Объект.свойство, название или источник" aria-label="Поиск в X-Ray"
                        yoyo:post="filter" yoyo:on="input delay:350ms changed" />
            <div class="md-ui-actions">
                <x-md.badge :tone="$paused ? 'neutral' : 'success'">{{ $paused ? 'Просмотр на паузе' : 'Обновление каждые 2 с' }}</x-md.badge>
                <x-md.button variant="secondary" :icon="$paused ? 'player-play' : 'player-pause'" yoyo:post="togglePause">{{ $paused ? 'Продолжить' : 'Пауза' }}</x-md.button>
            </div>
        </div>

        @if ($tab === 'timers')
            @include('yoyo.xray-timers')
        @elseif ($tab === 'cycles')
            @include('yoyo.xray-cycles')
        @else
            @if ($tab === 'property')
                @include('yoyo.xray-properties')
            @elseif ($tab === 'event')
                @include('yoyo.xray-events')
            @elseif ($tab === 'debug')
                @include('yoyo.xray-debug')
            @else
                @include('yoyo.xray-activity')
            @endif
            <div class="md-xray-footer"><span class="md-table__muted">Последние 72 часа · {{ count($rows) }} записей · страница {{ $page }}</span><div class="md-ui-actions">
                <x-md.button variant="ghost" size="sm" icon="chevron-left" yoyo:post="previousPage" :disabled="$page <= 1">Новее</x-md.button>
                <x-md.button variant="ghost" size="sm" icon-after="chevron-right" yoyo:post="nextPage" :disabled="!$more">Ранее</x-md.button>
            </div></div>
            @if ($tab === 'event')
                <x-md.card title="События и подписчики" subtitle="События ядра, модули и привязанные сценарии">
                    <x-md.table label="Зарегистрированные события и подписчики">
                        <x-slot:head><th scope="col">Событие</th><th scope="col">Назначение / подписчик</th></x-slot:head>
                        <x-slot:body>
                            @foreach ($subscriptions as $subscription)
                                <tr><td><code>{{ $subscription['name'] }}</code></td><td>{{ $subscription['consumer'] }}</td></tr>
                            @endforeach
                        </x-slot:body>
                    </x-md.table>
                </x-md.card>
            @endif
        @endif
    @endif
        </div>
    </div>
</div>
