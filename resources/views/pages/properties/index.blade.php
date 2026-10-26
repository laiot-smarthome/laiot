@php
    $typeLabels = ['string' => 'Строка', 'int' => 'Число', 'float' => 'Дробное', 'bool' => 'Да/Нет'];
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Дом"
        icon="adjustments"
        title="Свойства"
        lede="Свойства принадлежат классу и наследуются его объектами. Глобальные свойства доступны всем объектам."
        :meta="[
            ['icon' => 'adjustments', 'label' => 'Свойств', 'value' => count($properties)],
            ['icon' => 'hierarchy', 'label' => 'Классов', 'value' => count($classes)],
        ]">
        <x-slot:actions>
            <x-md.button href="/properties/create" icon="plus">Новое свойство</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    <x-md.table label="Свойства">
        <x-slot:toolbar>
            <form class="md-ui-search" method="get" action="/properties" role="search">
                <input class="md-input" type="search" name="q" value="{{ $search }}"
                       placeholder="Поиск по названию, описанию, OnChange" aria-label="Поиск свойств" autocomplete="off">
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
                    <a class="md-btn md-btn--ghost" href="/properties">Сбросить</a>
                @endif
            </form>
            <div class="md-table__meta">Найдено: {{ count($properties) }}</div>
        </x-slot:toolbar>

        @if ($properties === [])
            <x-slot:empty>
                <div class="md-empty">
                    <span class="md-empty__icon"><i class="ti ti-adjustments" aria-hidden="true"></i></span>
                    <div class="md-empty__title">{{ $search !== '' || $classId > 0 ? 'Свойства не найдены' : 'Свойств пока нет' }}</div>
                    <p class="md-empty__text">{{ $search !== '' || $classId > 0 ? 'Измените запрос или сбросьте фильтр.' : 'Добавьте свойство класса: например, «temperature» типа «Дробное».' }}</p>
                    <div class="md-empty__actions">
                        <a class="md-btn md-btn--primary" href="/properties/create">
                            <i class="ti ti-plus" aria-hidden="true"></i><span class="md-btn__label">Новое свойство</span>
                        </a>
                    </div>
                </div>
            </x-slot:empty>
        @else
            <x-slot:head>
                <th scope="col">Свойство</th>
                <th scope="col">Класс</th>
                <th scope="col">Тип</th>
                <th scope="col">История</th>
                <th scope="col">OnChange</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>

            <x-slot:body>
                @foreach ($properties as $property)
                    <tr>
                        <td>
                            <div class="md-table__name">
                                <span class="md-table__icon"><i class="ti ti-adjustments" aria-hidden="true"></i></span>
                                <div>
                                    <a href="/properties/{{ $property['id'] }}/edit"><strong>{{ $property['title'] }}</strong></a>
                                    @if ($property['description'] !== '')
                                        <small>{{ $property['description'] }}</small>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            @if ($property['class_id'] > 0 && !empty($classNames[$property['class_id']]))
                                <x-md.badge tone="outline">{{ $classNames[$property['class_id']] }}</x-md.badge>
                            @else
                                <span class="md-table__muted">Глобальное</span>
                            @endif
                        </td>
                        <td><x-md.badge tone="neutral">{{ $typeLabels[$property['data_type']] ?? $property['data_type'] }}</x-md.badge></td>
                        <td>
                            @if ($property['keep_history'])
                                <x-md.badge tone="success">ведётся</x-md.badge>
                            @else
                                <span class="md-table__muted">нет</span>
                            @endif
                        </td>
                        <td>
                            @if ($property['on_change'] !== '')
                                <code class="md-table__code">{{ $property['on_change'] }}</code>
                            @else
                                <span class="md-table__muted">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="md-table__actions">
                                <x-md.icon-button href="/properties/{{ $property['id'] }}/edit" icon="pencil" label="Открыть свойство" size="sm" />
                                <form method="post" action="/properties/{{ $property['id'] }}/delete" class="md-inline-form"
                                      data-md-confirm="Удалить свойство «{{ $property['title'] }}»? Значения останутся в БД.">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.icon-button type="submit" icon="trash" label="Удалить свойство" variant="danger" size="sm" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        @endif
    </x-md.table>
</div>
