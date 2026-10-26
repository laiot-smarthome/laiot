@php
    $typeTones = ['property.changed' => 'info', 'handler.method' => 'neutral'];
    $statusLabel = static fn (string $value): string => $statusLabels[$value] ?? $value;
    $statusTone = static fn (string $value): string => $statusTones[$value] ?? 'neutral';
    $typeLabel = static fn (string $value): string => $typeLabels[$value] ?? $value;
@endphp
<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="activity" /> core · events</div>
            <h1 class="md-ui-hero__title">События</h1>
            <p class="md-ui-hero__lede">Цепочка свойства: изменилось значение → сработал OnChange-метод → следующее свойство.</p>
        </div>
    </section>

    <x-md.hint
        tone="info"
        title="Как читается журнал"
        :steps="[
            'Запись значения свойства — строка «Свойство»; видно старое и новое значение.',
            'Вызов OnChange-метода свойства — строка «Обработчик»; видно результат выполнения.',
            'Если метод записал другое свойство, его шаг идёт с большей глубиной и ссылкой на родителя.',
            'Повтор свойства в цепочке останавливает обработчик: значение сохраняется, шаг помечается «Цикл остановлен».',
        ]"
    />

    <x-md.card title="Поиск">
        <form method="get" action="/module/events" class="md-form md-form--inline">
            <x-md.input name="q" label="Объект, свойство, обработчик или значение" :value="$search" />
            <x-md.select name="type" label="Тип">
                <option value="">Все</option>
                @foreach (array_keys($typeLabels) as $value)
                    <option value="{{ $value }}" @selected($type === $value)>{{ $typeLabel($value) }}</option>
                @endforeach
            </x-md.select>
            <x-md.select name="status" label="Статус">
                <option value="">Все</option>
                @foreach (array_keys($statusLabels) as $value)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $statusLabel($value) }}</option>
                @endforeach
            </x-md.select>
            <x-md.button type="submit" variant="secondary" icon="search">Найти</x-md.button>
            <x-md.button href="/module/events" variant="ghost">Сбросить</x-md.button>
        </form>
    </x-md.card>

    <x-md.card title="Журнал" :subtitle="'Шагов: ' . count($rows)">
        @if ($rows === [])
            <x-md.empty-state
                title="Событий нет"
                text="Измените значение свойства: запись, история и вызов OnChange-метода появятся здесь."
                icon="activity"
            />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead>
                    <tr>
                        <th>Время</th>
                        <th>Шаг</th>
                        <th>Тип</th>
                        <th>Объект / свойство</th>
                        <th>Значение</th>
                        <th>Обработчик</th>
                        <th>Статус</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $row)
                        <tr @if ($row['chain'] !== []) title="Цепочка: {{ implode(' → ', $row['chain']) }}" @endif>
                            <td class="md-table__muted">{{ $row['created_at'] }}</td>
                            <td class="md-table__muted">{{ $row['depth'] }}</td>
                            <td><x-md.badge :tone="$typeTones[$row['type']] ?? 'neutral'">{{ $typeLabel($row['type']) }}</x-md.badge></td>
                            <td>
                                {{ $row['object_title'] }}
                                <code>{{ $row['property_title'] }}</code>
                            </td>
                            <td>
                                @if ($row['type'] === 'property.changed')
                                    <code>{{ $row['old_value'] === '' ? '—' : $row['old_value'] }}</code>
                                    <span class="md-table__muted">→</span>
                                    <code>{{ $row['new_value'] === '' ? '—' : $row['new_value'] }}</code>
                                @else
                                    <code>{{ $row['new_value'] === '' ? '—' : $row['new_value'] }}</code>
                                @endif
                            </td>
                            <td>
                                @if ($row['handler'] !== '')
                                    <code>{{ $row['handler'] }}</code>
                                @endif
                                @if ($row['message'] !== '')
                                    <div class="md-table__muted" title="{{ $row['message'] }}">{{ mb_strimwidth($row['message'], 0, 90, '…') }}</div>
                                @endif
                            </td>
                            <td>
                                <x-md.badge :tone="$statusTone($row['status'])">{{ $statusLabel($row['status']) }}</x-md.badge>
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
    </x-md.card>
</div>
