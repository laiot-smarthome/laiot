<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="list-check" /> core · queue</div>
            <h1 class="md-ui-hero__title">Очередь заданий</h1>
            <p class="md-ui-hero__lede">DatabaseQueue: pending, reserved и failed. Worker — `php bin/mydomo queue:work`.</p>
        </div>
    </section>

    <div class="md-ui-kpi-grid">
        <x-md.kpi label="Pending" :value="(string)$stats['pending']" icon="clock" tone="info" meta="default" />
        <x-md.kpi label="Reserved" :value="(string)$stats['reserved']" icon="loader" tone="warning" meta="default" />
        <x-md.kpi label="Failed" :value="(string)$stats['failed']" icon="alert-triangle" tone="danger" meta="default" />
    </div>

    <x-md.card title="Pending-задания" subtitle="Ждут worker">
        @if ($pending === [])
            <x-md.empty-state title="Нет ожидающих" text="Очередь пуста." icon="check" />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead><tr><th>ID</th><th>Job</th><th>Попытки</th><th>Доступно</th></tr></thead>
                    <tbody>
                    @foreach ($pending as $job)
                        <tr>
                            <td>{{ $job['id'] }}</td>
                            <td><code>{{ $job['job'] }}</code></td>
                            <td>{{ $job['attempts'] }}</td>
                            <td class="md-table__muted">{{ $job['available_at'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-md-card>

    <x-md.card title="Failed-задания" subtitle="Повтор или окончательное удаление">
        @if ($failed === [])
            <x-md.empty-state title="Очередь чистая" text="Failed-заданий нет." icon="check" />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead><tr><th>ID</th><th>Job</th><th>Попытки</th><th>Ошибка</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($failed as $job)
                        <tr>
                            <td>{{ $job['id'] }}</td>
                            <td><code>{{ $job['job'] }}</code></td>
                            <td>{{ $job['attempts'] }}</td>
                            <td class="md-table__muted">{{ $job['error'] }}</td>
                            <td>
                                <form method="post" action="/module/jobs/failed/{{ $job['id'] }}/retry" class="md-inline-form">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.button type="submit" variant="secondary" size="sm" icon="refresh">Повторить</x-md.button>
                                </form>
                                <form method="post" action="/module/jobs/failed/{{ $job['id'] }}/forget" class="md-inline-form">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.button type="submit" variant="danger" size="sm" icon="trash">Удалить</x-md.button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-md.card>
</div>
