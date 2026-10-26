@php
    $formatTime = static fn (?string $value): string => ($value === null || $value === '' || strlen($value) < 16)
        ? '—'
        : substr($value, 8, 2) . '.' . substr($value, 5, 2) . ' · ' . substr($value, 11, 5);
    $handled = 0;
    $failed = 0;
    foreach ($rows as $row) {
        if ($row['type'] === 'handler.method') {
            $handled++;
        }
        if (in_array($row['status'], ['error', 'missing'], true)) {
            $failed++;
        }
    }
@endphp

<div>
    <div class="md-table-wrap" aria-label="Журнал событий">
        <div class="md-table__toolbar">
            <div class="md-ui-search">
                <input class="md-input" type="search" name="search" value="{{ $search }}"
                       placeholder="Объект, свойство, обработчик или значение"
                       aria-label="Поиск событий" autocomplete="off"
                       yoyo:on="keyup delay:300ms changed">
                <select class="md-table__filter" name="type" aria-label="Тип события" yoyo>
                    <option value="">Все типы</option>
                    @foreach (array_keys($typeLabels) as $value)
                        <option value="{{ $value }}" @selected($type === $value)>{{ $typeLabels[$value] }}</option>
                    @endforeach
                </select>
                <select class="md-table__filter" name="status" aria-label="Статус события" yoyo>
                    <option value="">Все статусы</option>
                    @foreach (array_keys($statusLabels) as $value)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $statusLabels[$value] }}</option>
                    @endforeach
                </select>
                <button type="button" class="md-btn md-btn--secondary" yoyo:get="reset" aria-label="Сбросить фильтры">
                    <i class="ti ti-filter-off" aria-hidden="true"></i><span class="md-btn__label">Сбросить</span>
                </button>
            </div>
            <div class="md-table__meta">
                Шагов: {{ count($rows) }} · обработчиков: {{ $handled }} · с ошибкой: {{ $failed }}
            </div>
        </div>

        @if ($rows === [])
            <div class="md-empty">
                <span class="md-empty__icon"><i class="ti ti-bolt" aria-hidden="true"></i></span>
                <div class="md-empty__title">Событий не найдено</div>
                <p class="md-empty__text">Измените фильтры или сбросьте поиск.</p>
            </div>
        @else
            <div class="md-table__scroll">
                <table class="md-table">
                    <thead>
                    <tr>
                        <th scope="col">Время</th>
                        <th scope="col">Тип</th>
                        <th scope="col">Объект / свойство</th>
                        <th scope="col">Значение</th>
                        <th scope="col">Обработчик</th>
                        <th scope="col">Статус</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $row)
                        <tr @if ($row['chain'] !== []) title="Цепочка: {{ implode(' → ', $row['chain']) }}" @endif>
                            <td>
                                <span class="md-table__muted">{{ $formatTime($row['created_at']) }}</span>
                                @if ($row['depth'] > 0)
                                    <span class="md-table__muted">· шаг {{ $row['depth'] }}</span>
                                @endif
                            </td>
                            <td><span class="md-badge md-badge--{{ $row['type'] === 'property.changed' ? 'info' : 'neutral' }}">{{ $typeLabels[$row['type']] ?? $row['type'] }}</span></td>
                            <td>
                                <div class="md-table__name">
                                    <span class="md-table__icon"><i class="ti ti-box" aria-hidden="true"></i></span>
                                    <div>
                                        <strong>{{ $row['object_title'] !== '' ? $row['object_title'] : '—' }}</strong>
                                        <small>{{ $row['property_title'] !== '' ? $row['property_title'] : '—' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($row['type'] === 'property.changed')
                                    <span class="md-table__muted">{{ $row['old_value'] !== '' ? $row['old_value'] : '—' }}</span>
                                    <i class="ti ti-arrow-right" aria-hidden="true"></i>
                                @endif
                                <span class="md-table__value">{{ $row['new_value'] !== '' ? $row['new_value'] : '—' }}</span>
                            </td>
                            <td>
                                @if ($row['handler'] !== '')
                                    <code class="md-table__code">{{ $row['handler'] }}</code>
                                @endif
                                @if ($row['message'] !== '')
                                    <div class="md-table__muted" title="{{ $row['message'] }}">{{ mb_strimwidth($row['message'], 0, 70, '…') }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="md-badge md-badge--{{ $statusTones[$row['status']] ?? 'neutral' }}">
                                    {{ $statusLabels[$row['status']] ?? $row['status'] }}
                                </span>
                                @if ($row['source'] !== '')
                                    <div class="md-table__muted">{{ $row['source'] }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
