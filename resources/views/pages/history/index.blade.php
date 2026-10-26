<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Дом"
        icon="history"
        title="История значений"
        lede="Записи свойств с включённой историей. Хранится в property_history и не зависит от журнала событий."
        :meta="[
            ['icon' => 'list', 'label' => 'Записей', 'value' => count($rows)],
        ]" />

    <x-md.table label="История значений">
        <x-slot:toolbar>
            <form class="md-ui-search" method="get" action="/history" role="search">
                <input class="md-input" type="search" name="q" value="{{ $search }}"
                       placeholder="Объект, свойство или значение" aria-label="Поиск истории" autocomplete="off">
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
                    <a class="md-btn md-btn--ghost" href="/history">Сбросить</a>
                @endif
            </form>
            <div class="md-table__meta">Найдено: {{ count($rows) }}</div>
        </x-slot:toolbar>

        @if ($rows === [])
            <x-slot:empty>
                <div class="md-empty">
                    <span class="md-empty__icon"><i class="ti ti-history" aria-hidden="true"></i></span>
                    <div class="md-empty__title">{{ $search !== '' || $objectId > 0 ? 'Записей не найдено' : 'Истории пока нет' }}</div>
                    <p class="md-empty__text">{{ $search !== '' || $objectId > 0 ? 'Измените запрос или сбросьте фильтры.' : 'Включите «Хранить историю» у свойства и запишите значение.' }}</p>
                </div>
            </x-slot:empty>
        @else
            <x-slot:head>
                <th scope="col">Когда</th>
                <th scope="col">Объект</th>
                <th scope="col">Свойство</th>
                <th scope="col">Значение</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>

            <x-slot:body>
                @foreach ($rows as $row)
                    <tr>
                        <td><span class="md-table__muted">{{ $row['created_at'] }}</span></td>
                        <td>
                            <a href="/objects/{{ $row['object_id'] }}/edit?tab=history">{{ $row['object_title'] }}</a>
                        </td>
                        <td><code class="md-table__code">{{ $row['property_title'] }}</code></td>
                        <td><span class="md-table__value" title="{{ $row['value'] }}">{{ $row['value'] }}</span></td>
                        <td>
                            <div class="md-table__actions">
                                <form method="post" action="/history/{{ $row['id'] }}/delete" class="md-inline-form"
                                      data-md-confirm="Удалить запись истории?">
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
