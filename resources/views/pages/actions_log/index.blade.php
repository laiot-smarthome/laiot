<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Система"
        icon="list-details"
        title="Журнал действий"
        lede="Кто и что менял в панели: вход, правка справочников, удаление записей."
        :meta="[
            ['icon' => 'list', 'label' => 'Записей', 'value' => count($entries)],
            ['icon' => 'category', 'label' => 'Типов', 'value' => count($types)],
        ]" />

    <x-md.table label="Журнал действий">
        <x-slot:toolbar>
            <form class="md-ui-search" method="get" action="/journal" role="search">
                <input class="md-input" type="search" name="q" value="{{ $search }}"
                       placeholder="Поиск по событию и пользователю" aria-label="Поиск по журналу" autocomplete="off">
                <select class="md-table__filter" name="type" aria-label="Тип события" onchange="this.form.submit()">
                    <option value="">Все типы</option>
                    @foreach ($types as $typeName)
                        <option value="{{ $typeName }}" @selected($type === $typeName)>{{ $typeName }}</option>
                    @endforeach
                </select>
                <button class="md-btn md-btn--secondary" type="submit">
                    <i class="ti ti-search" aria-hidden="true"></i><span class="md-btn__label">Найти</span>
                </button>
                @if ($search !== '' || $type !== '')
                    <a class="md-btn md-btn--ghost" href="/journal">Сбросить</a>
                @endif
            </form>
            <div class="md-table__meta">Найдено: {{ count($entries) }}</div>
        </x-slot:toolbar>

        @if ($entries === [])
            <x-slot:empty>
                <div class="md-empty">
                    <span class="md-empty__icon"><i class="ti ti-list-details" aria-hidden="true"></i></span>
                    <div class="md-empty__title">{{ $search !== '' || $type !== '' ? 'Записей не найдено' : 'Журнал пуст' }}</div>
                    <p class="md-empty__text">{{ $search !== '' || $type !== '' ? 'Измените фильтры или сбросьте поиск.' : 'Записи появятся после входа и изменений в панели.' }}</p>
                </div>
            </x-slot:empty>
        @else
            <x-slot:head>
                <th scope="col">Когда</th>
                <th scope="col">Тип</th>
                <th scope="col">Событие</th>
                <th scope="col">Пользователь</th>
                <th scope="col">Терминал</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>

            <x-slot:body>
                @foreach ($entries as $entry)
                    <tr>
                        <td><span class="md-table__muted">{{ $entry['added'] ?? '' }}</span></td>
                        <td><x-md.badge tone="outline">{{ $entry['type'] ?? '' }}</x-md.badge></td>
                        <td>{{ $entry['title'] ?? '' }}</td>
                        <td>{{ ($entry['user'] ?? '') !== '' ? $entry['user'] : '—' }}</td>
                        <td>
                            <span class="md-table__muted">
                                {{ ($entry['terminal'] ?? '') !== '' ? $entry['terminal'] : 'web' }}
                                @if (($entry['ip'] ?? '') !== '') · {{ $entry['ip'] }} @endif
                            </span>
                        </td>
                        <td>
                            <div class="md-table__actions">
                                <form method="post" action="/journal/{{ $entry['id'] }}/delete" class="md-inline-form"
                                      data-md-confirm="Удалить запись журнала?">
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
