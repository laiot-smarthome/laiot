@php
    $ranges = ['24h' => '24 часа', '7d' => '7 дней', '30d' => '30 дней', 'all' => 'Всё время'];
    $formatNumber = static fn (mixed $value): string => $value === null
        ? '—'
        : (is_float($value) ? number_format($value, 2, ',', ' ') : (string)$value);
    $formatTime = static fn (string $value): string => strlen($value) < 16
        ? '—'
        : substr($value, 8, 2) . '.' . substr($value, 5, 2) . ' · ' . substr($value, 11, 5);
    $dialogId = 'mdHistoryTitle-' . $objectId . '-' . $propertyId;
@endphp

<div>
    <button type="button" class="md-icon-btn md-icon-btn--sm" yoyo:get="open"
            title="История значения" aria-label="История значения {{ $title }}">
        <i class="ti ti-chart-line" aria-hidden="true"></i>
    </button>

    @if ($open && $data !== null)
        <div class="md-history-overlay" role="dialog" aria-modal="true" aria-labelledby="{{ $dialogId }}">
            <div class="md-history-backdrop" yoyo:get="close" aria-hidden="true"></div>

            <aside class="md-history-panel">
                <header class="md-history-panel__header">
                    <div class="md-history-panel__heading">
                        <div class="md-history-panel__eyebrow">История значения</div>
                        <h2 class="md-history-panel__title" id="{{ $dialogId }}">
                            {{ $data['object']['title'] }}.{{ $data['property']['title'] }}
                        </h2>
                    </div>
                    <button type="button" class="md-icon-btn md-icon-btn--sm" yoyo:get="close" aria-label="Закрыть историю">
                        <i class="ti ti-x" aria-hidden="true"></i>
                    </button>
                </header>

                <div class="md-history-panel__body">
                    <div class="md-history__ranges" role="group" aria-label="Период истории">
                        @foreach ($ranges as $rangeId => $rangeLabel)
                            <button type="button"
                                    class="md-history__range @if ($range === $rangeId) is-active @endif"
                                    yoyo:get="setRange('{{ $rangeId }}')"
                                    aria-pressed="{{ $range === $rangeId ? 'true' : 'false' }}">
                                {{ $rangeLabel }}
                            </button>
                        @endforeach
                    </div>

                    <div class="md-history__current">
                        <span class="md-history__current-label">Текущее значение</span>
                        <strong class="md-history__value">{{ $data['current'] === '' ? 'не задано' : $data['current'] }}</strong>
                    </div>

                    <div class="md-history__stats">
                        <div class="md-history__stat">
                            <span class="md-history__stat-label">Записей</span>
                            <strong class="md-history__stat-value">{{ $data['stats']['count'] }}</strong>
                        </div>
                        @if ($data['is_numeric'])
                            <div class="md-history__stat">
                                <span class="md-history__stat-label">Минимум</span>
                                <strong class="md-history__stat-value">{{ $formatNumber($data['stats']['min']) }}</strong>
                            </div>
                            <div class="md-history__stat">
                                <span class="md-history__stat-label">Максимум</span>
                                <strong class="md-history__stat-value">{{ $formatNumber($data['stats']['max']) }}</strong>
                            </div>
                            <div class="md-history__stat">
                                <span class="md-history__stat-label">Среднее</span>
                                <strong class="md-history__stat-value">{{ $formatNumber($data['stats']['avg']) }}</strong>
                            </div>
                        @else
                            <div class="md-history__stat">
                                <span class="md-history__stat-label">Последнее</span>
                                <strong class="md-history__stat-value">{{ $data['stats']['last'] !== null && $data['stats']['last'] !== '' ? $data['stats']['last'] : '—' }}</strong>
                            </div>
                        @endif
                    </div>

                    @if (!$data['is_numeric'])
                        <div class="md-history-chart__empty">Свойство не числовое — график не строится.</div>
                    @elseif (count($data['chart']) < 2)
                        <div class="md-history-chart__empty">Для графика нужно минимум две записи за период.</div>
                    @else
                        <div class="md-history-chart" data-md-chart
                             data-md-chart-name="{{ $data['property']['title'] }}"
                             data-md-chart-points="{{ json_encode($data['chart'], JSON_UNESCAPED_UNICODE) }}"></div>
                    @endif

                    <h3 class="md-history__section-title">Записи</h3>
                    @if ($data['history'] === [])
                        <div class="md-history__state">Записей за выбранный период нет.</div>
                    @else
                        <div class="md-history__table">
                            <table class="md-table">
                                <thead>
                                <tr>
                                    <th scope="col">Когда</th>
                                    <th scope="col">Значение</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach (array_slice($data['history'], 0, 100) as $row)
                                    <tr>
                                        <td class="md-table__muted">{{ $formatTime($row['created_at']) }}</td>
                                        <td>{{ $row['value'] === '' ? '—' : $row['value'] }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </aside>
        </div>
    @endif
</div>
