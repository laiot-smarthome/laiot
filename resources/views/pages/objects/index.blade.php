<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Дом"
        icon="box"
        title="Объекты"
        lede="Каталог объектов умного дома: класс, расположение и значения свойств в одном месте."
        :meta="[
            ['icon' => 'box', 'label' => 'Объектов', 'value' => count($objects)],
            ['icon' => 'hierarchy', 'label' => 'Классов', 'value' => count($classes)],
            ['icon' => 'map-pin', 'label' => 'Локаций', 'value' => count($locations)],
        ]">
        <x-slot:actions>
            <x-md.button href="/objects/create" icon="plus">Добавить объект</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    <x-md.table label="Объекты">
        <x-slot:toolbar>
            <form class="md-ui-search" method="get" action="/objects" role="search">
                <input class="md-input" type="search" name="q" value="{{ $search }}"
                       placeholder="Поиск по названию и описанию" aria-label="Поиск объектов" autocomplete="off">
                <select class="md-table__filter" name="class_id" aria-label="Фильтр по классу" onchange="this.form.submit()">
                    <option value="0">Все классы</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class['id'] }}" @selected($classId === $class['id'])>{{ $class['title'] }}</option>
                    @endforeach
                </select>
                <select class="md-table__filter" name="location_id" aria-label="Фильтр по локации" onchange="this.form.submit()">
                    <option value="0">Все локации</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location['id'] }}" @selected($locationId === $location['id'])>{{ $location['title'] }}</option>
                    @endforeach
                </select>
                <button class="md-btn md-btn--secondary" type="submit">
                    <i class="ti ti-search" aria-hidden="true"></i><span class="md-btn__label">Найти</span>
                </button>
                @if ($search !== '' || $classId > 0 || $locationId > 0)
                    <a class="md-btn md-btn--ghost" href="/objects">Сбросить</a>
                @endif
            </form>
            <div class="md-table__meta">Найдено: {{ count($objects) }}</div>
        </x-slot:toolbar>

        @if ($objects === [])
            <x-slot:empty>
                <div class="md-empty">
                    <span class="md-empty__icon"><i class="ti ti-box" aria-hidden="true"></i></span>
                    <div class="md-empty__title">{{ $search !== '' || $classId > 0 || $locationId > 0 ? 'Ничего не найдено' : 'Объектов пока нет' }}</div>
                    <p class="md-empty__text">{{ $search !== '' || $classId > 0 || $locationId > 0 ? 'Измените запрос или сбросьте фильтры.' : 'Добавьте первый объект: комнату, датчик или устройство.' }}</p>
                    <div class="md-empty__actions">
                        @if ($search !== '' || $classId > 0 || $locationId > 0)
                            <a class="md-btn md-btn--secondary" href="/objects">Сбросить фильтры</a>
                        @else
                            <a class="md-btn md-btn--primary" href="/objects/create">
                                <i class="ti ti-plus" aria-hidden="true"></i><span class="md-btn__label">Добавить объект</span>
                            </a>
                        @endif
                    </div>
                </div>
            </x-slot:empty>
        @else
            <x-slot:head>
                <th scope="col">Объект</th>
                <th scope="col">Класс</th>
                <th scope="col">Расположение</th>
                <th scope="col">Обновлён</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>

            <x-slot:body>
                @foreach ($objects as $object)
                    <tr>
                        <td>
                            <div class="md-table__name">
                                <span class="md-table__icon"><i class="ti ti-box" aria-hidden="true"></i></span>
                                <div>
                                    <a href="/objects/{{ $object['id'] }}/edit"><strong>{{ $object['title'] }}</strong></a>
                                    @if ($object['description'] !== '')
                                        <small>{{ $object['description'] }}</small>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            @if (!empty($classNames[$object['class_id']]))
                                <x-md.badge tone="outline">{{ $classNames[$object['class_id']] }}</x-md.badge>
                            @else
                                <span class="md-table__muted">Без класса</span>
                            @endif
                        </td>
                        <td>
                            @if (!empty($locationNames[$object['location_id']]))
                                {{ $locationNames[$object['location_id']] }}
                            @else
                                <span class="md-table__muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="md-table__muted">
                                {{ $object['updated_at'] !== '' ? substr($object['updated_at'], 8, 2) . '.' . substr($object['updated_at'], 5, 2) . ' ' . substr($object['updated_at'], 11, 5) : '—' }}
                            </span>
                        </td>
                        <td>
                            <div class="md-table__actions">
                                <x-md.icon-button href="/objects/{{ $object['id'] }}/edit" icon="pencil" label="Открыть объект" size="sm" />
                                <form method="post" action="/objects/{{ $object['id'] }}/delete" class="md-inline-form"
                                      data-md-confirm="Удалить объект «{{ $object['title'] }}»? Записи истории останутся в БД.">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.icon-button type="submit" icon="trash" label="Удалить объект" variant="danger" size="sm" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        @endif
    </x-md.table>
</div>
