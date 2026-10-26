@php
    $levelTones = ['error' => 'danger', 'critical' => 'danger', 'warning' => 'warning', 'notice' => 'info', 'info' => 'neutral'];
    $levelCounts = ['danger' => 0, 'warning' => 0, 'info' => 0];
    foreach ($errors as $error) {
        $tone = $levelTones[$error['level']] ?? 'info';
        $levelCounts[$tone] = ($levelCounts[$tone] ?? 0) + 1;
    }
    $formatTime = static fn (?string $value): string => ($value === null || $value === '' || strlen($value) < 16)
        ? '—'
        : substr($value, 8, 2) . '.' . substr($value, 5, 2) . ' · ' . substr($value, 11, 5);
    $unique = count($groups);
    $repeats = count($errors);
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Система"
        icon="alert-triangle"
        title="Ошибки"
        lede="Журнал исключений ядра: level, сообщение и request_id для поиска в логах. Одинаковые сообщения собраны в группы."
        :meta="[
            ['icon' => 'list', 'label' => 'Записей', 'value' => $repeats],
            ['icon' => 'layers', 'label' => 'Групп', 'value' => $unique],
            ['icon' => 'alert-octagon', 'label' => 'Ошибок', 'value' => $levelCounts['danger']],
            ['icon' => 'alert-triangle', 'label' => 'Предупреждений', 'value' => $levelCounts['warning']],
        ]" />

    @if ($done !== '')
        <x-md.alert type="success" :text="$done" />
    @endif

    @if ($message !== '')
        <x-md.alert type="info" title="Показаны записи одной группы"
                    :text="'Сообщение: ' . $message" />
        <div class="md-ui-actions">
            <x-md.button href="/errors" variant="secondary" icon="arrow-left">Все группы</x-md.button>
        </div>
    @endif

    <x-md.table :label="$flat || $message !== '' ? 'Записи ошибок' : 'Группы ошибок'">
        <x-slot:toolbar>
            <form class="md-ui-search" method="get" action="/errors" role="search">
                <input class="md-input" type="search" name="q" value="{{ $search }}"
                       placeholder="Поиск по сообщению и request_id" aria-label="Поиск ошибок" autocomplete="off">
                <select class="md-table__filter" name="level" aria-label="Уровень" onchange="this.form.submit()">
                    <option value="">Все уровни</option>
                    @foreach (['error', 'warning', 'critical', 'notice', 'info'] as $levelName)
                        <option value="{{ $levelName }}" @selected($level === $levelName)>{{ $levelName }}</option>
                    @endforeach
                </select>
                <button class="md-btn md-btn--secondary" type="submit">
                    <i class="ti ti-search" aria-hidden="true"></i><span class="md-btn__label">Найти</span>
                </button>
                @if ($search !== '' || $level !== '')
                    <a class="md-btn md-btn--ghost" href="/errors">Сбросить</a>
                @endif
            </form>
            <div class="md-table__meta">
                @if ($flat || $message !== '')
                    <a href="/errors">Группы</a> · записей: {{ count($errors) }}
                @else
                    Групп: {{ count($groups) }} · <a href="/errors?flat=1">плоский список</a>
                @endif
            </div>
        </x-slot:toolbar>

        @if ($errors === [])
            <x-slot:empty>
                <div class="md-empty">
                    <span class="md-empty__icon"><i class="ti ti-circle-check" aria-hidden="true"></i></span>
                    <div class="md-empty__title">{{ $search !== '' || $level !== '' ? 'Ошибок не найдено' : 'Ошибок нет' }}</div>
                    <p class="md-empty__text">{{ $search !== '' || $level !== '' ? 'Измените фильтры или сбросьте поиск.' : 'Журнал пополняется автоматически при исключениях ядра.' }}</p>
                </div>
            </x-slot:empty>
        @elseif (!$flat && $message === '')
            <x-slot:head>
                <th scope="col">Уровень</th>
                <th scope="col">Сообщение</th>
                <th scope="col">Повторов</th>
                <th scope="col">Последняя</th>
                <th scope="col">Request ID</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>
            <x-slot:body>
                @foreach ($groups as $group)
                    <tr>
                        <td><x-md.badge :tone="$levelTones[$group['level']] ?? 'neutral'">{{ $group['level'] }}</x-md.badge></td>
                        <td>
                            <span class="md-table__value" style="max-width: 52ch" title="{{ $group['message'] }}">{{ mb_strimwidth($group['message'], 0, 110, '…') }}</span>
                        </td>
                        <td><x-md.badge tone="neutral">{{ $group['count'] }}</x-md.badge></td>
                        <td><span class="md-table__muted">{{ $formatTime($group['last_at']) }}</span></td>
                        <td>
                            @if ($group['request_id'] !== '')
                                <code class="md-table__code">{{ $group['request_id'] }}</code>
                            @else
                                <span class="md-table__muted">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="md-table__actions">
                                <x-md.icon-button
                                    :href="'/errors?message=' . rawurlencode($group['message']) . '&level=' . rawurlencode($group['level'])"
                                    icon="list" label="Показать записи группы" size="sm" />
                                <form method="post" action="/errors/group-delete" class="md-inline-form"
                                      data-md-confirm="Удалить все {{ $group['count'] }} записей этой группы?">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <input type="hidden" name="level" value="{{ $group['level'] }}">
                                    <input type="hidden" name="message" value="{{ $group['message'] }}">
                                    <x-md.icon-button type="submit" icon="trash" label="Удалить группу" variant="danger" size="sm" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        @else
            <x-slot:head>
                <th scope="col">Когда</th>
                <th scope="col">Уровень</th>
                <th scope="col">Сообщение</th>
                <th scope="col">Request ID</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>
            <x-slot:body>
                @foreach ($errors as $error)
                    <tr>
                        <td><span class="md-table__muted">{{ $formatTime($error['created_at']) }}</span></td>
                        <td><x-md.badge :tone="$levelTones[$error['level']] ?? 'neutral'">{{ $error['level'] }}</x-md.badge></td>
                        <td><span title="{{ $error['message'] }}">{{ mb_strimwidth($error['message'], 0, 160, '…') }}</span></td>
                        <td>
                            @if ($error['request_id'] !== '')
                                <code class="md-table__code">{{ $error['request_id'] }}</code>
                            @else
                                <span class="md-table__muted">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="md-table__actions">
                                <form method="post" action="/errors/{{ $error['id'] }}/delete" class="md-inline-form"
                                      data-md-confirm="Удалить запись об ошибке?">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.icon-button type="submit" icon="trash" label="Удалить запись" variant="danger" size="sm" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        @endif
    </x-md.table>
</div>
