@php
    $totalObjects = 0;
    foreach ($objectsByClass as $rows) {
        $totalObjects += count($rows);
    }
    $totalProperties = array_sum($propertyCounts);
    $totalMethods = array_sum($methodCounts);
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Дом"
        icon="hierarchy"
        title="Классы"
        lede="Классы задают свойства и методы, которые наследуют объекты. Раскройте класс, чтобы увидеть его объекты."
        :meta="[
            ['icon' => 'hierarchy', 'label' => 'Классов', 'value' => count($classes)],
            ['icon' => 'adjustments', 'label' => 'Свойств', 'value' => $totalProperties],
            ['icon' => 'code', 'label' => 'Методов', 'value' => $totalMethods],
            ['icon' => 'box', 'label' => 'Объектов', 'value' => $totalObjects],
        ]">
        <x-slot:actions>
            <x-md.button href="/classes/create" icon="plus">Новый класс</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    <x-md.table label="Классы" variant="tree">
        <x-slot:toolbar>
            <form class="md-ui-search" method="get" action="/classes" role="search">
                <input class="md-input" type="search" name="q" value="{{ $search }}"
                       placeholder="Поиск класса" aria-label="Поиск класса" autocomplete="off">
                <button class="md-btn md-btn--secondary" type="submit">
                    <i class="ti ti-search" aria-hidden="true"></i><span class="md-btn__label">Найти</span>
                </button>
                @if ($search !== '')
                    <a class="md-btn md-btn--ghost" href="/classes">Сбросить</a>
                @endif
            </form>
            <div class="md-table__meta">Найдено: {{ count($classes) }}</div>
        </x-slot:toolbar>

        @if ($classes === [])
            <x-slot:empty>
                <div class="md-empty">
                    <span class="md-empty__icon"><i class="ti ti-hierarchy" aria-hidden="true"></i></span>
                    <div class="md-empty__title">{{ $search !== '' ? 'Классы не найдены' : 'Классов пока нет' }}</div>
                    <p class="md-empty__text">{{ $search !== '' ? 'Измените запрос или сбросьте поиск.' : 'Создайте первый класс: «Датчик», «Свет», «Термостат».' }}</p>
                    <div class="md-empty__actions">
                        <a class="md-btn md-btn--primary" href="/classes/create">
                            <i class="ti ti-plus" aria-hidden="true"></i><span class="md-btn__label">Новый класс</span>
                        </a>
                    </div>
                </div>
            </x-slot:empty>
        @else
            <x-slot:head>
                <th scope="col">Класс</th>
                <th scope="col">Свойства</th>
                <th scope="col">Методы</th>
                <th scope="col">Объекты</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>

            <x-slot:body>
                @foreach ($classes as $class)
                    @php
                        $classObjects = $objectsByClass[$class['id']] ?? [];
                        $propertyCount = $propertyCounts[$class['id']] ?? 0;
                        $methodCount = $methodCounts[$class['id']] ?? 0;
                    @endphp
                    <tbody x-data="{open: false}">
                    <tr data-md-class-row data-class-id="{{ $class['id'] }}">
                        <td>
                            <div class="md-tree-table__cell" style="--md-tree-level: 0">
                                @if ($classObjects !== [])
                                    <button type="button" class="md-tree-table__toggle"
                                            x-bind:aria-expanded="open ? 'true' : 'false'"
                                            @click="open = !open"
                                            aria-label="Показать объекты класса {{ $class['title'] }}">
                                        <i class="ti ti-chevron-right" aria-hidden="true"></i>
                                    </button>
                                @else
                                    <span class="md-tree-table__toggle-placeholder" aria-hidden="true"></span>
                                @endif
                                <span class="md-tree-table__icon"><i class="ti ti-hierarchy-2" aria-hidden="true"></i></span>
                                <span class="md-tree-table__text">
                                    <a class="md-tree-table__title" href="/classes/{{ $class['id'] }}/edit">{{ $class['title'] }}</a>
                                    <span class="md-tree-table__desc">
                                        @if ($classObjects === [])
                                            Нет объектов
                                        @else
                                            {{ count($classObjects) }} {{ count($classObjects) === 1 ? 'объект' : (count($classObjects) < 5 ? 'объекта' : 'объектов') }}
                                        @endif
                                    </span>
                                </span>
                            </div>
                        </td>
                        <td><a href="/properties?class_id={{ $class['id'] }}">{{ $propertyCount }}</a></td>
                        <td><a href="/methods?class_id={{ $class['id'] }}">{{ $methodCount }}</a></td>
                        <td><span class="md-tree-table__count">{{ count($classObjects) }}</span></td>
                        <td>
                            <div class="md-table__actions">
                                <x-md.icon-button href="/classes/{{ $class['id'] }}/edit" icon="pencil" label="Открыть класс" size="sm" />
                                <form method="post" action="/classes/{{ $class['id'] }}/delete" class="md-inline-form"
                                      data-md-confirm="Удалить класс «{{ $class['title'] }}»? Объекты класса останутся, но потеряют свойства.">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.icon-button type="submit" icon="trash" label="Удалить класс" variant="danger" size="sm" />
                                </form>
                            </div>
                        </td>
                    </tr>
                    @if ($classObjects !== [])
                        <tr class="md-tree-table__detail" x-show="open" x-cloak>
                            <td colspan="5">
                                <div class="md-tree-table__panel">
                                    <div class="md-tree-objects">
                                        @foreach ($classObjects as $classObject)
                                            <a class="md-tree-object" href="/objects/{{ $classObject['id'] }}/edit">
                                                <span class="md-tree-object__icon"><i class="ti ti-box" aria-hidden="true"></i></span>
                                                <span class="md-tree-object__body"><strong>{{ $classObject['title'] }}</strong></span>
                                            </a>
                                        @endforeach
                                    </div>
                                    <div class="md-tree-panel-actions">
                                        <a href="/objects?class_id={{ $class['id'] }}">Все объекты класса</a>
                                        <a href="/objects/create">Добавить объект</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                    </tbody>
                @endforeach
            </x-slot:body>
        @endif
    </x-md.table>
</div>
