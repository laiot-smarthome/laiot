<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Дом"
        icon="code"
        title="Методы"
        lede="Методы классов — пользовательский код. Отсюда он редактируется, но не запускается: вызов идёт из сценариев и API."
        :meta="[
            ['icon' => 'code', 'label' => 'Методов', 'value' => count($methods)],
            ['icon' => 'hierarchy', 'label' => 'Классов', 'value' => count($classes)],
        ]">
        <x-slot:actions>
            <x-md.button href="/methods/create" icon="plus">Новый метод</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    <x-md.table label="Методы">
        <x-slot:toolbar>
            <form class="md-ui-search" method="get" action="/methods" role="search">
                <input class="md-input" type="search" name="q" value="{{ $search }}"
                       placeholder="Поиск по названию и описанию" aria-label="Поиск методов" autocomplete="off">
                <select class="md-table__filter" name="class_id" aria-label="Фильтр по классу" onchange="this.form.submit()">
                    <option value="0">Все классы</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class['id'] }}" @selected($classId === $class['id'])>{{ $class['title'] }}</option>
                    @endforeach
                </select>
                <button class="md-btn md-btn--secondary" type="submit">
                    <i class="ti ti-search" aria-hidden="true"></i><span class="md-btn__label">Найти</span>
                </button>
                @if ($search !== '' || $classId > 0)
                    <a class="md-btn md-btn--ghost" href="/methods">Сбросить</a>
                @endif
            </form>
            <div class="md-table__meta">Найдено: {{ count($methods) }}</div>
        </x-slot:toolbar>

        @if ($methods === [])
            <x-slot:empty>
                <div class="md-empty">
                    <span class="md-empty__icon"><i class="ti ti-code" aria-hidden="true"></i></span>
                    <div class="md-empty__title">{{ $search !== '' || $classId > 0 ? 'Методы не найдены' : 'Методов пока нет' }}</div>
                    <p class="md-empty__text">{{ $search !== '' || $classId > 0 ? 'Измените запрос или сбросьте фильтр.' : 'Добавьте метод класса — например, обработчик изменения свойства.' }}</p>
                    <div class="md-empty__actions">
                        <a class="md-btn md-btn--primary" href="/methods/create">
                            <i class="ti ti-plus" aria-hidden="true"></i><span class="md-btn__label">Новый метод</span>
                        </a>
                    </div>
                </div>
            </x-slot:empty>
        @else
            <x-slot:head>
                <th scope="col">Метод</th>
                <th scope="col">Класс</th>
                <th scope="col">Описание</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>

            <x-slot:body>
                @foreach ($methods as $method)
                    <tr>
                        <td>
                            <div class="md-table__name">
                                <span class="md-table__icon"><i class="ti ti-code" aria-hidden="true"></i></span>
                                <div><a href="/methods/{{ $method['id'] }}/edit"><strong>{{ $method['title'] }}</strong></a></div>
                            </div>
                        </td>
                        <td>
                            @if ($method['class_id'] > 0 && !empty($classNames[$method['class_id']]))
                                <x-md.badge tone="outline">{{ $classNames[$method['class_id']] }}</x-md.badge>
                            @else
                                <span class="md-table__muted">Без класса</span>
                            @endif
                        </td>
                        <td>{{ $method['description'] !== '' ? $method['description'] : '—' }}</td>
                        <td>
                            <div class="md-table__actions">
                                <x-md.icon-button href="/methods/{{ $method['id'] }}/edit" icon="pencil" label="Открыть метод" size="sm" />
                                <form method="post" action="/methods/{{ $method['id'] }}/delete" class="md-inline-form"
                                      data-md-confirm="Удалить метод «{{ $method['title'] }}»? Сценарии, которые его вызывают, начнут падать.">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.icon-button type="submit" icon="trash" label="Удалить метод" variant="danger" size="sm" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        @endif
    </x-md.table>
</div>
