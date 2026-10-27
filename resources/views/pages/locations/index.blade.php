<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Дом"
        icon="map-pin"
        title="Локации"
        lede="Дома, этажи и комнаты. Комната получает класс, объект и свойства активности; этаж — подкласс Rooms для будущей свёртки."
        :meta="[
            ['icon' => 'home', 'label' => 'Домов', 'value' => count(array_filter($locations, static fn (array $row): bool => $row['kind'] === 'house'))],
            ['icon' => 'stairs', 'label' => 'Этажей', 'value' => count(array_filter($locations, static fn (array $row): bool => $row['kind'] === 'floor'))],
            ['icon' => 'door', 'label' => 'Комнат', 'value' => count(array_filter($locations, static fn (array $row): bool => $row['kind'] === 'room'))],
        ]">
        <x-slot:actions>
            <x-md.button href="/locations/create" icon="plus">Новая локация</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    @if ($error !== null)
        <x-md.alert type="danger" title="Не удалось удалить локацию" :text="$error" />
    @endif

    <x-md.table label="Локации">
        <x-slot:toolbar>
            <form class="md-ui-search" method="get" action="/locations" role="search">
                <input class="md-input" type="search" name="q" value="{{ $search }}"
                       placeholder="Поиск локации" aria-label="Поиск локации" autocomplete="off">
                <button class="md-btn md-btn--secondary" type="submit">
                    <i class="ti ti-search" aria-hidden="true"></i><span class="md-btn__label">Найти</span>
                </button>
                @if ($search !== '')
                    <a class="md-btn md-btn--ghost" href="/locations">Сбросить</a>
                @endif
            </form>
            <div class="md-table__meta">Найдено: {{ count($locations) }}</div>
        </x-slot:toolbar>

        @if ($locations === [])
            <x-slot:empty>
                <div class="md-empty">
                    <span class="md-empty__icon"><i class="ti ti-map-pin" aria-hidden="true"></i></span>
                    <div class="md-empty__title">{{ $search !== '' ? 'Локации не найдены' : 'Локаций пока нет' }}</div>
                    <p class="md-empty__text">{{ $search !== '' ? 'Измените запрос или сбросьте поиск.' : 'Добавьте дом, этаж или комнату: кухню, гостиную, спальню.' }}</p>
                    <div class="md-empty__actions">
                        <a class="md-btn md-btn--primary" href="/locations/create">
                            <i class="ti ti-plus" aria-hidden="true"></i><span class="md-btn__label">Новая локация</span>
                        </a>
                    </div>
                </div>
            </x-slot:empty>
        @else
            <x-slot:head>
                <th scope="col">Локация</th>
                <th scope="col">Тип</th>
                <th scope="col">Объектов</th>
                <th scope="col">Приоритет</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>

            <x-slot:body>
                @foreach ($locations as $location)
                    @php
                        $kindIcon = match ($location['kind']) {
                            'house' => 'home',
                            'floor' => 'stairs',
                            default => 'door',
                        };
                        $kindLabel = $kinds ?? ['house' => 'Дом', 'floor' => 'Этаж', 'room' => 'Комната'];
                    @endphp
                    <tr>
                        <td>
                            <div class="md-table__name" style="padding-left: {{ (int)$location['depth'] * 20 }}px">
                                <span class="md-table__icon"><i class="ti ti-{{ $kindIcon }}" aria-hidden="true"></i></span>
                                <div>
                                    <a href="/locations/{{ $location['id'] }}/edit"><strong>{{ $location['title'] }}</strong></a>
                                    @if ((int)$location['depth'] > 0)
                                        <div class="md-ui-muted">{{ $location['path'] }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td><x-md.badge tone="neutral">{{ $kindLabel[$location['kind']] ?? $location['kind'] }}</x-md.badge></td>
                        <td><span class="md-tree-table__count">{{ $objectCounts[$location['id']] ?? 0 }}</span></td>
                        <td><x-md.badge tone="neutral">{{ $location['priority'] }}</x-md.badge></td>
                        <td>
                            <div class="md-table__actions">
                                <x-md.icon-button href="/locations/{{ $location['id'] }}/edit" icon="pencil" label="Открыть локацию" size="sm" />
                                <form method="post" action="/locations/{{ $location['id'] }}/delete" class="md-inline-form"
                                      data-md-confirm="Удалить локацию «{{ $location['title'] }}»? Объекты останутся без расположения.">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.icon-button type="submit" icon="trash" label="Удалить локацию" variant="danger" size="sm" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        @endif
    </x-md.table>
</div>
