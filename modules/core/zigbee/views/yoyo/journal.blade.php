@php
    $rows = $result['rows'];
    $pages = (int)$result['pages'];
    $current = (int)$result['page'];
    $total = (int)$result['total'];
    $perPage = (int)$result['per_page'];
    $from = $total > 0 ? (($current - 1) * $perPage) + 1 : 0;
    $to = min($current * $perPage, $total);
    $bridgeOptions = ['0' => 'Все экземпляры'];
    foreach ($bridges as $item) {
        $bridgeOptions[(string)$item['id']] = (string)$item['title'];
    }
    $tones = ['error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
@endphp

<div class="md-card">
    <header class="md-card__head">
        <div class="md-card__titles">
            <div class="md-card__eyebrow">События</div>
            <h2 class="md-card__title">Последние записи</h2>
            <p class="md-card__subtitle">Показано {{ $from }}–{{ $to }} из {{ $total }}.</p>
        </div>
        <button type="button" class="md-btn md-btn--sm md-btn--ghost" yoyo:get="resetFilters" yoyo:spinning.attr="disabled">
            <x-md.icon name="filter-off" /><span class="md-btn__label">Сбросить</span>
        </button>
    </header>
    <div class="md-card__body">
        <div class="md-ui-form-grid">
            <x-md.input name="search" label="Поиск" :value="$search" placeholder="Топик или сообщение"
                        yoyo:on="keyup delay:400ms changed" />
            <x-md.select name="bridge" label="Экземпляр" :selected="$bridge" :options="$bridgeOptions" yoyo:on="change" />
            <x-md.select name="level" label="Уровень" :selected="$level"
                         :options="['' => 'Любой', 'info' => 'Информация', 'warning' => 'Предупреждение', 'error' => 'Ошибка']"
                         yoyo:on="change" />
        </div>
    </div>

    @if ($rows === [])
        <div class="md-card__body">
            <x-md.empty-state icon="history" title="Записей нет" text="Журнал заполняется при обнаружении устройств, доставке и ошибках." compact />
        </div>
    @else
        <div class="md-table-wrap">
            <div class="md-table__scroll">
                <table class="md-table">
                    <thead>
                    <tr>
                        <th>Время</th>
                        <th>Уровень</th>
                        <th>Экземпляр</th>
                        <th>Устройство</th>
                        <th>Топик</th>
                        <th>Сообщение</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ date('d.m.Y H:i:s', (int)strtotime((string)$row['added'])) }}</td>
                            <td><x-md.badge :tone="$tones[(string)$row['level']] ?? 'neutral'">{{ $row['level'] }}</x-md.badge></td>
                            <td>{{ $row['bridge_title'] }}</td>
                            <td>{{ $row['device_title'] !== '' ? $row['device_title'] : '—' }}</td>
                            <td><code class="md-table__code">{{ $row['topic'] }}</code></td>
                            <td>{{ $row['message'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($pages > 1)
        <div class="md-card__body md-ui-actions">
            @for ($page = 1; $page <= $pages; $page++)
                <button type="button" class="md-btn md-btn--sm {{ $page === $current ? 'md-btn--primary' : 'md-btn--ghost' }}"
                        yoyo:get="paginate" yoyo:val.page="{{ $page }}" yoyo:spinning.attr="disabled">{{ $page }}</button>
            @endfor
        </div>
    @endif
</div>
