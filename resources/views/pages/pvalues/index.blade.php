<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Дом"
        icon="list"
        title="Значения свойств"
        lede="Текущие значения всех объектов. Правка идёт через общий сервис: история, журнал и OnChange срабатывают как из сценария."
        :meta="[
            ['icon' => 'list', 'label' => 'Значений', 'value' => count($values)],
        ]" />

    <x-md.table label="Значения свойств">
        <x-slot:toolbar>
            <form class="md-ui-search" method="get" action="/values" role="search">
                <input class="md-input" type="search" name="q" value="{{ $search }}"
                       placeholder="Объект, свойство или значение" aria-label="Поиск значений" autocomplete="off">
                <select class="md-table__filter" name="object_id" aria-label="Объект" onchange="this.form.submit()">
                    <option value="0">Все объекты</option>
                    @foreach ($objects as $object)
                        <option value="{{ $object['id'] }}" @selected($objectId === $object['id'])>{{ $object['title'] }}</option>
                    @endforeach
                </select>
                <button class="md-btn md-btn--secondary" type="submit">
                    <i class="ti ti-search" aria-hidden="true"></i><span class="md-btn__label">Найти</span>
                </button>
                @if ($search !== '' || $objectId > 0)
                    <a class="md-btn md-btn--ghost" href="/values">Сбросить</a>
                @endif
            </form>
            <div class="md-table__meta">Найдено: {{ count($values) }}</div>
        </x-slot:toolbar>

        @if ($values === [])
            <x-slot:empty>
                <div class="md-empty">
                    <span class="md-empty__icon"><i class="ti ti-list" aria-hidden="true"></i></span>
                    <div class="md-empty__title">{{ $search !== '' || $objectId > 0 ? 'Значения не найдены' : 'Значений пока нет' }}</div>
                    <p class="md-empty__text">{{ $search !== '' || $objectId > 0 ? 'Измените запрос или сбросьте фильтры.' : 'Откройте объект и задайте значения свойств — они появятся здесь.' }}</p>
                </div>
            </x-slot:empty>
        @else
            <x-slot:head>
                <th scope="col">Объект</th>
                <th scope="col">Свойство</th>
                <th scope="col">Значение</th>
                <th scope="col">Обновлено</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>

            <x-slot:body>
                @foreach ($values as $value)
                    <tr>
                        <td>
                            <div class="md-table__name">
                                <span class="md-table__icon"><i class="ti ti-box" aria-hidden="true"></i></span>
                                <div>
                                    <a href="/objects/{{ $value['object_id'] }}/edit"><strong>{{ $value['object_title'] }}</strong></a>
                                </div>
                            </div>
                        </td>
                        <td><code class="md-table__code">{{ $value['property_title'] }}</code></td>
                        <td>
                            <form method="post" action="/values/{{ $value['id'] }}" class="md-inline-form">
                                <input type="hidden" name="_token" value="{{ $csrf }}">
                                <input class="md-input" type="text" name="value" value="{{ $value['value'] }}"
                                       aria-label="Значение {{ $value['object_title'] }}.{{ $value['property_title'] }}">
                                <x-md.button type="submit" variant="secondary" size="sm" icon="check">Сохранить</x-md.button>
                            </form>
                        </td>
                        <td><span class="md-table__muted">{{ $value['updated_at'] }}</span></td>
                        <td>
                            <div class="md-table__actions">
                                @yoyo('property-history', [
                                    'objectId' => $value['object_id'],
                                    'propertyId' => $value['property_id'],
                                    'title' => $value['object_title'] . '.' . $value['property_title'],
                                ])
                                <form method="post" action="/values/{{ $value['id'] }}/delete" class="md-inline-form"
                                      data-md-confirm="Удалить значение {{ $value['object_title'] }}.{{ $value['property_title'] }}?">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.icon-button type="submit" icon="trash" label="Удалить значение" variant="danger" size="sm" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        @endif
    </x-md.table>
</div>
