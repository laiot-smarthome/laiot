@php
    $isSearch = $search !== '';
@endphp

<div class="md-ui-page md-ui-page--stack"
     x-data="{
         expanded: {},
         isVisible(path) {
             const ids = path ? path.split(',').filter(Boolean) : [];
             return ids.every((id) => this.expanded[id]);
         }
     }">
    <x-md.page-hero
        eyebrow="Дом"
        icon="hierarchy"
        title="Классы"
        lede="Класс задаёт свойства и методы, подклассы наследуют их, а объекты получают значения. Всё остальное открывается из класса."
        :meta="[
            ['icon' => 'hierarchy', 'label' => 'Классов', 'value' => count($classes)],
            ['icon' => 'git-branch', 'label' => 'Подклассов', 'value' => $totalSubclasses],
            ['icon' => 'box', 'label' => 'Объектов', 'value' => $totalObjects],
            ['icon' => 'adjustments', 'label' => 'Своих свойств', 'value' => $totalProperties],
            ['icon' => 'code', 'label' => 'Своих методов', 'value' => $totalMethods],
        ]">
        <x-slot:actions>
            <x-md.button href="/classes/create" icon="plus">Новый класс</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    @if ($error !== null)
        <x-md.alert type="danger" title="Класс не удалён" :text="$error" />
    @endif

    <x-md.table label="Классы" variant="tree">
        <x-slot:toolbar>
            <form class="md-ui-search" method="get" action="/classes" role="search">
                <input class="md-input" type="search" name="q" value="{{ $search }}"
                       placeholder="Поиск класса" aria-label="Поиск класса" autocomplete="off">
                <button class="md-btn md-btn--secondary" type="submit">
                    <i class="ti ti-search" aria-hidden="true"></i><span class="md-btn__label">Найти</span>
                </button>
                @if ($isSearch)
                    <a class="md-btn md-btn--ghost" href="/classes">Сбросить</a>
                @endif
            </form>
            <div class="md-table__meta">Найдено: {{ count($rows) }}</div>
        </x-slot:toolbar>

        @if ($rows === [])
            <x-slot:empty>
                <div class="md-empty">
                    <span class="md-empty__icon"><i class="ti ti-hierarchy" aria-hidden="true"></i></span>
                    <div class="md-empty__title">{{ $isSearch ? 'Классы не найдены' : 'Классов пока нет' }}</div>
                    <p class="md-empty__text">{{ $isSearch ? 'Измените запрос или сбросьте поиск.' : 'Создайте первый класс: «Датчик», «Свет», «Термостат».' }}</p>
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
                @foreach ($rows as $row)
                    @php
                        $classId = (int)$row['id'];
                        $classObjects = $objectsByClass[$classId] ?? [];
                        $props = $propertyCounts[$classId] ?? ['own' => 0, 'inherited' => 0, 'effective' => 0];
                        $meths = $methodCounts[$classId] ?? ['own' => 0, 'inherited' => 0, 'effective' => 0];
                        $directObjects = (int)($objectCounts[$classId] ?? 0);
                        $subtreeObjects = (int)($subtreeObjectCounts[$classId] ?? $directObjects);
                        $hasToggle = $row['has_children'] || $classObjects !== [];
                    @endphp
                    <tr class="md-tree-table__row"
                        x-show="isVisible('{{ implode(',', $row['ancestors']) }}')"
                        @if ($row['ancestors'] !== []) x-cloak @endif
                        data-md-class-row
                        data-class-id="{{ $classId }}"
                        data-parent-id="{{ $row['parent_id'] }}"
                        data-has-children="{{ $row['has_children'] ? '1' : '0' }}"
                        x-bind:class="{ 'is-expanded': expanded[{{ $classId }}] }">
                        <td>
                            <div class="md-tree-table__cell" style="--md-tree-level: {{ $row['depth'] }}">
                                @if ($hasToggle)
                                    <button type="button" class="md-tree-table__toggle"
                                            x-bind:aria-expanded="expanded[{{ $classId }}] ? 'true' : 'false'"
                                            @click="expanded[{{ $classId }}] = !expanded[{{ $classId }}]"
                                            aria-label="Показать подклассы и объекты класса {{ $row['title'] }}">
                                        <i class="ti ti-chevron-right" aria-hidden="true"></i>
                                    </button>
                                @else
                                    <span class="md-tree-table__toggle-placeholder" aria-hidden="true"></span>
                                @endif
                                <span class="md-tree-table__icon"><i class="ti ti-hierarchy-2" aria-hidden="true"></i></span>
                                <span class="md-tree-table__text">
                                    <a class="md-tree-table__title" href="/classes/{{ $classId }}/edit">{{ $row['title'] }}</a>
                                    <span class="md-tree-table__desc">
                                        @if ($isSearch)
                                            {{ $row['path'] }}
                                        @elseif ($row['has_children'])
                                            {{ $row['children_count'] }} {{ $row['children_count'] === 1 ? 'подкласс' : ($row['children_count'] < 5 ? 'подкласса' : 'подклассов') }}
                                        @else
                                            Без подклассов
                                        @endif
                                        @if ($props['inherited'] > 0)
                                            · унаследовано свойств: {{ $props['inherited'] }}
                                        @endif
                                    </span>
                                </span>
                            </div>
                        </td>
                        <td>
                            <a href="/classes/{{ $classId }}/edit?tab=properties">{{ $props['effective'] }}</a>
                            @if ($props['inherited'] > 0)
                                <small class="md-table__muted" title="Из них унаследовано: {{ $props['inherited'] }}">+{{ $props['inherited'] }}</small>
                            @endif
                        </td>
                        <td>
                            <a href="/classes/{{ $classId }}/edit?tab=methods">{{ $meths['effective'] }}</a>
                            @if ($meths['inherited'] > 0)
                                <small class="md-table__muted" title="Из них унаследовано: {{ $meths['inherited'] }}">+{{ $meths['inherited'] }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="md-tree-table__count">{{ $directObjects }}</span>
                            @if ($subtreeObjects > $directObjects)
                                <small class="md-table__muted" title="С объектами подклассов">+{{ $subtreeObjects - $directObjects }}</small>
                            @endif
                        </td>
                        <td>
                            <div class="md-table__actions">
                                <x-md.icon-button href="/classes/{{ $classId }}/edit" icon="pencil" label="Открыть класс" size="sm" />
                                <form method="post" action="/classes/{{ $classId }}/delete" class="md-inline-form"
                                      data-md-confirm="Удалить класс «{{ $row['title'] }}»?">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.icon-button type="submit" icon="trash" label="Удалить класс" variant="danger" size="sm" />
                                </form>
                            </div>
                        </td>
                    </tr>
                    @if ($classObjects !== [])
                        <tr class="md-tree-table__detail @if ($row['has_children']) md-tree-table__detail--with-children @endif"
                            x-show="expanded[{{ $classId }}]" x-cloak>
                            <td colspan="5">
                                <div class="md-tree-table__panel" style="--md-tree-level: {{ $row['depth'] }}">
                                    <div class="md-tree-table__panel-head">Объекты класса</div>
                                    <div class="md-tree-objects">
                                        @foreach ($classObjects as $classObject)
                                            <a class="md-tree-object" href="/objects/{{ $classObject['id'] }}/edit">
                                                <span class="md-tree-object__icon"><i class="ti ti-box" aria-hidden="true"></i></span>
                                                <span class="md-tree-object__body"><strong>{{ $classObject['title'] }}</strong></span>
                                            </a>
                                        @endforeach
                                    </div>
                                    <div class="md-tree-panel-actions">
                                        <a href="/classes/{{ $classId }}/edit?tab=objects">Все объекты класса</a>
                                        <a href="/objects/create?class_id={{ $classId }}">Добавить объект</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </x-slot:body>
        @endif
    </x-md.table>
</div>
