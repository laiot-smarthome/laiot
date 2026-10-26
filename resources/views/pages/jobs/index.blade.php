<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Автоматизация"
        icon="stack"
        title="Очередь заданий"
        lede="Фоновые задания нового ядра: отложенные вызовы, повторы и dead-letter с ручным восстановлением."
        :meta="[
            ['icon' => 'clock', 'label' => 'В ожидании', 'value' => $stats['pending']],
            ['icon' => 'player-play', 'label' => 'Выполняются', 'value' => $stats['reserved']],
            ['icon' => 'alert-triangle', 'label' => 'С ошибкой', 'value' => $stats['failed']],
        ]" />

    <div class="md-ui-kpi-grid">
        <x-md.kpi label="В ожидании" :value="(string)$stats['pending']" icon="clock" tone="info"
                  meta="Готовы к выполнению" />
        <x-md.kpi label="Выполняются" :value="(string)$stats['reserved']" icon="player-play" tone="primary"
                  meta="Зарезервированы воркером" />
        <x-md.kpi label="С ошибкой" :value="(string)$stats['failed']" icon="alert-triangle" :tone="$stats['failed'] > 0 ? 'danger' : 'success'"
                  meta="Требуют повтора или удаления" />
    </div>

    <x-md.card title="Готовы к выполнению" :subtitle="'Заданий: ' . count($pending)">
        @if ($pending === [])
            <x-md.empty-state icon="clock" title="Очередь пуста"
                              text="Здесь появятся задания, которые ждут воркера." />
        @else
            <x-md.table label="Задания в ожидании">
                <x-slot:head>
                    <th scope="col">ID</th>
                    <th scope="col">Задание</th>
                    <th scope="col">Попытка</th>
                    <th scope="col">Доступно с</th>
                </x-slot:head>
                <x-slot:body>
                    @foreach ($pending as $job)
                        <tr>
                            <td><span class="md-table__muted">#{{ $job['id'] }}</span></td>
                            <td><code class="md-table__code">{{ $job['job'] }}</code></td>
                            <td>{{ $job['attempts'] }}</td>
                            <td><span class="md-table__muted">{{ $job['available_at'] }}</span></td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-md.table>
            <p class="md-ui-muted">Воркер запускается командой <code>php bin/mydomo queue:work</code>; в Docker — сервисом из <code>docker-compose.yml</code>.</p>
        @endif
    </x-md.card>

    <x-md.card title="Упавшие задания" :subtitle="'Заданий: ' . count($failed)">
        @if ($failed === [])
            <x-md.empty-state icon="circle-check" title="Упавших заданий нет"
                              text="После исчерпания попыток задание попадёт сюда с текстом ошибки." />
        @else
            <x-md.table label="Упавшие задания">
                <x-slot:head>
                    <th scope="col">ID</th>
                    <th scope="col">Задание</th>
                    <th scope="col">Попыток</th>
                    <th scope="col">Ошибка</th>
                    <th scope="col">Когда</th>
                    <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
                </x-slot:head>
                <x-slot:body>
                    @foreach ($failed as $job)
                        <tr>
                            <td><span class="md-table__muted">#{{ $job['id'] }}</span></td>
                            <td><code class="md-table__code">{{ $job['job'] }}</code></td>
                            <td>{{ $job['attempts'] }}</td>
                            <td>
                                <span class="md-table__value" title="{{ $job['error'] }}">{{ $job['error'] !== '' ? $job['error'] : '—' }}</span>
                            </td>
                            <td><span class="md-table__muted">{{ $job['failed_at'] }}</span></td>
                            <td>
                                <div class="md-table__actions">
                                    <form method="post" action="/jobs/failed/{{ $job['id'] }}/retry" class="md-inline-form">
                                        <input type="hidden" name="_token" value="{{ $csrf }}">
                                        <x-md.button type="submit" variant="secondary" size="sm" icon="refresh">Повторить</x-md.button>
                                    </form>
                                    <form method="post" action="/jobs/failed/{{ $job['id'] }}/forget" class="md-inline-form"
                                          data-md-confirm="Удалить упавшее задание #{{ $job['id'] }}?">
                                        <input type="hidden" name="_token" value="{{ $csrf }}">
                                        <x-md.icon-button type="submit" icon="trash" label="Удалить задание" variant="danger" size="sm" />
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-md.table>
        @endif
    </x-md.card>
</div>
