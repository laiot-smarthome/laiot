@php
    $hasClasses = $classes !== [];
@endphp

<div class="md-ui-page md-ui-page--stack md-classes-page"
     x-data="mdClassesTree(@js($tree))">
    <x-md.page-hero
        eyebrow="Дом"
        icon="hierarchy"
        title="Классы"
        lede="Класс задаёт свойства и методы, подклассы наследуют их, а объекты получают значения и расположение. На компьютере объекты видны прямо в дереве, на телефоне — в панели класса."
        :meta="$hasClasses ? [
            ['icon' => 'hierarchy', 'label' => 'Классов', 'value' => count($classes)],
            ['icon' => 'git-branch', 'label' => 'Подклассов', 'value' => $totalSubclasses],
            ['icon' => 'box', 'label' => 'Объектов', 'value' => $totalObjects],
            ['icon' => 'adjustments', 'label' => 'Своих свойств', 'value' => $totalProperties],
            ['icon' => 'code', 'label' => 'Своих методов', 'value' => $totalMethods],
        ] : []">
        <x-slot:actions>
            <x-md.button href="/classes/create" icon="plus">Новый класс</x-md.button>
            @if ($hasClasses)
                <x-md.button href="/objects/create" variant="secondary" icon="box">Новый объект</x-md.button>
            @endif
        </x-slot:actions>
    </x-md.page-hero>

    @if ($error !== null)
        <x-md.notify type="danger" title="Класс не удалён" :text="$error" />
    @endif

    @if (!$hasClasses)
        <x-md.empty-state icon="hierarchy" title="Классов пока нет"
                          text="Создайте первый класс: «Датчик», «Свет», «Термостат». Затем добавьте в него объекты и укажите их расположение.">
            <x-slot:actions>
                <x-md.button href="/classes/create" icon="plus">Новый класс</x-md.button>
            </x-slot:actions>
        </x-md.empty-state>
    @else
        <div class="md-ui-kpi-grid">
            <x-md.kpi label="Классы" :value="count($classes)" tone="primary" icon="hierarchy"
                      :meta="$rootClasses . ' корневых · ' . $totalSubclasses . ' дочерних'" />
            <x-md.kpi label="Объекты" :value="$totalObjects" tone="info" icon="box"
                      meta="У каждого объекта своё расположение" />
            <x-md.kpi label="Методы классов" :value="$totalMethods" tone="warning" icon="braces"
                      meta="Наследуются подклассами" />
            <x-md.kpi label="Свойства классов" :value="$totalProperties" tone="success" icon="adjustments"
                      meta="Значения живут у объектов" />
        </div>

        <x-md.table label="Классы и объекты" variant="tree" class="md-classes-table">
            <x-slot:toolbar>
                <div class="md-classes-toolbar">
                    <div class="md-table__search">
                        <i class="ti ti-search" aria-hidden="true"></i>
                        <input type="search" x-model="query" placeholder="Поиск класса, объекта или локации"
                               aria-label="Поиск класса, объекта или локации" autocomplete="off">
                    </div>

                    <select class="md-table__filter" x-model="filter" aria-label="Фильтр по объектам">
                        <option value="">Все классы</option>
                        <option value="with">С объектами</option>
                        <option value="empty">Без объектов</option>
                    </select>

                    <div class="md-classes-toolbar__actions">
                        <button type="button" class="md-btn md-btn--ghost md-btn--sm" @click="expandAll()"
                                aria-label="Раскрыть все классы">
                            <i class="ti ti-chevrons-down" aria-hidden="true"></i><span class="md-btn__label">Развернуть</span>
                        </button>
                        <button type="button" class="md-btn md-btn--ghost md-btn--sm" @click="collapseAll()"
                                aria-label="Свернуть все классы">
                            <i class="ti ti-chevrons-up" aria-hidden="true"></i><span class="md-btn__label">Свернуть</span>
                        </button>
                    </div>

                    <div class="md-classes-toolbar__meta">
                        Показано <span x-text="shownCount"></span> из {{ count($classes) }}
                    </div>
                </div>
            </x-slot:toolbar>

            <x-slot:head>
                <th scope="col">Класс</th>
                <th scope="col" class="md-classes-col-count">Объекты</th>
                <th scope="col" class="md-classes-col-count">Методы</th>
                <th scope="col" class="md-classes-col-count">Свойства</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>

            <x-slot:body>
                @foreach ($rows as $row)
                    @php
                        $classId = (int)$row['id'];
                        $depth = (int)$row['depth'];
                        $classObjects = $objectsByClass[$classId] ?? [];
                        $objectTotal = count($classObjects);
                        $props = $propertyCounts[$classId] ?? ['own' => 0, 'inherited' => 0, 'effective' => 0];
                        $meths = $methodCounts[$classId] ?? ['own' => 0, 'inherited' => 0, 'effective' => 0];
                        $directObjects = (int)($objectCounts[$classId] ?? 0);
                        $subtreeObjects = (int)($subtreeObjectCounts[$classId] ?? $directObjects);
                        $hasToggle = $row['has_children'] || $classObjects !== [];
                        $description = (string)($row['description'] ?? '');
                    @endphp

                    <tr class="md-tree-table__row"
                        x-show="isClassVisible(klass({{ $classId }}))"
                        @if ($depth > 0) x-cloak @endif
                        data-md-class-row
                        data-class-id="{{ $classId }}"
                        data-parent-id="{{ $row['parent_id'] }}"
                        data-has-children="{{ $row['has_children'] ? '1' : '0' }}"
                        :class="{ 'is-expanded': isExpanded({{ $classId }}) }">
                        <td>
                            <div class="md-tree-table__cell" style="--md-tree-level: {{ $depth }}">
                                @if ($hasToggle)
                                    <button type="button" class="md-tree-table__toggle"
                                            @click="toggle({{ $classId }})"
                                            :aria-expanded="isExpanded({{ $classId }}) ? 'true' : 'false'"
                                            aria-label="Показать объекты и подклассы класса {{ $row['title'] }}">
                                        <i class="ti ti-chevron-right" aria-hidden="true"></i>
                                    </button>
                                @else
                                    <span class="md-tree-table__toggle-placeholder" aria-hidden="true"></span>
                                @endif
                                <span class="md-tree-table__icon"><i class="ti ti-hierarchy-2" aria-hidden="true"></i></span>
                                <span class="md-tree-table__text">
                                    <a class="md-tree-table__title" href="/classes/{{ $classId }}/edit">{{ $row['title'] }}</a>
                                    <span class="md-tree-table__desc">
                                        @if ($description !== '')
                                            {{ $description }}
                                        @elseif ($row['has_children'])
                                            {{ $row['children_count'] }}
                                            {{ $row['children_count'] === 1 ? 'подкласс' : ($row['children_count'] < 5 ? 'подкласса' : 'подклассов') }}
                                        @else
                                            Без подклассов
                                        @endif
                                        @if ($objectTotal > 0)
                                            · {{ $objectTotal }}
                                            {{ $objectTotal === 1 ? 'объект' : ($objectTotal < 5 ? 'объекта' : 'объектов') }}
                                        @endif
                                    </span>
                                </span>
                            </div>
                        </td>
                        <td class="md-classes-col-count">
                            <a href="/classes/{{ $classId }}/edit?tab=objects">{{ $directObjects }}</a>
                            @if ($subtreeObjects > $directObjects)
                                <small class="md-table__muted" title="С объектами подклассов">+{{ $subtreeObjects - $directObjects }}</small>
                            @endif
                        </td>
                        <td class="md-classes-col-count">
                            <a href="/classes/{{ $classId }}/edit?tab=methods">{{ $meths['effective'] }}</a>
                            @if ($meths['inherited'] > 0)
                                <small class="md-table__muted" title="Из них унаследовано: {{ $meths['inherited'] }}">+{{ $meths['inherited'] }}</small>
                            @endif
                        </td>
                        <td class="md-classes-col-count">
                            <a href="/classes/{{ $classId }}/edit?tab=properties">{{ $props['effective'] }}</a>
                            @if ($props['inherited'] > 0)
                                <small class="md-table__muted" title="Из них унаследовано: {{ $props['inherited'] }}">+{{ $props['inherited'] }}</small>
                            @endif
                        </td>
                        <td>
                            <div class="md-table__actions">
                                <x-md.icon-button href="/classes/create?parent_id={{ $classId }}" icon="git-branch"
                                                  label="Добавить подкласс" size="sm" />
                                <x-md.icon-button href="/classes/{{ $classId }}/edit" icon="pencil"
                                                  label="Открыть класс" size="sm" />
                                <div class="md-kit-dropdown" x-data="{ open: false }" @click.outside="open = false">
                                    <button type="button" class="md-icon-btn md-icon-btn--sm"
                                            @click="open = !open" :aria-expanded="open.toString()"
                                            aria-haspopup="true" aria-label="Действия класса {{ $row['title'] }}">
                                        <i class="ti ti-dots" aria-hidden="true"></i>
                                    </button>
                                    <div class="md-kit-dropdown__panel" x-cloak x-show="open" x-transition.opacity>
                                        <a href="/classes/{{ $classId }}/edit?tab=properties"><i class="ti ti-adjustments" aria-hidden="true"></i>Свойства</a>
                                        <a href="/classes/{{ $classId }}/edit?tab=methods"><i class="ti ti-braces" aria-hidden="true"></i>Методы</a>
                                        <a href="/classes/{{ $classId }}/edit?tab=objects"><i class="ti ti-box" aria-hidden="true"></i>Объекты</a>
                                        <a href="/objects/create?class_id={{ $classId }}"><i class="ti ti-plus" aria-hidden="true"></i>Добавить объект</a>
                                        <form method="post" action="/classes/{{ $classId }}/delete"
                                              data-md-confirm="Удалить класс «{{ $row['title'] }}»? Удалить можно только класс без подклассов и объектов.">
                                            <input type="hidden" name="_token" value="{{ $csrf }}">
                                            <button type="submit"><i class="ti ti-trash" aria-hidden="true"></i>Удалить</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                    {{-- ПК: объекты — дочерние строки дерева. --}}
                    @foreach ($classObjects as $classObject)
                        <tr class="md-tree-table__row md-classes-object-row"
                            data-md-object-row
                            data-parent-id="{{ $classId }}"
                            x-show="isObjectRowVisible({{ $classId }}, {{ (int)$classObject['id'] }})" x-cloak>
                            <td>
                                <div class="md-tree-table__cell md-classes-object-cell"
                                     style="--md-tree-level: {{ $depth + 1 }}">
                                    <span class="md-tree-table__toggle-placeholder" aria-hidden="true"></span>
                                    <span class="md-tree-table__icon md-classes-object-icon"><i class="ti ti-box" aria-hidden="true"></i></span>
                                    <span class="md-tree-table__text">
                                        <a class="md-tree-table__title" href="/objects/{{ (int)$classObject['id'] }}/edit">{{ $classObject['title'] }}</a>
                                        <span class="md-tree-table__desc">
                                            @if (($classObject['description'] ?? '') !== '')
                                                {{ $classObject['description'] }}
                                            @else
                                                Объект класса {{ $row['title'] }}
                                            @endif
                                        </span>
                                    </span>
                                    @if (($classObject['location_title'] ?? '') !== '')
                                        <span class="md-badge md-badge--outline md-classes-object-location">
                                            <i class="ti ti-map-pin" aria-hidden="true"></i>{{ $classObject['location_title'] }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="md-classes-col-count"></td>
                            <td class="md-classes-col-count"></td>
                            <td class="md-classes-col-count"></td>
                            <td>
                                <div class="md-table__actions md-classes-object-actions">
                                    <x-md.icon-button href="/objects/{{ (int)$classObject['id'] }}/edit?tab=properties"
                                                      icon="adjustments" label="Свойства объекта" size="sm" />
                                    <x-md.icon-button href="/objects/{{ (int)$classObject['id'] }}/edit"
                                                      icon="pencil" label="Открыть объект" size="sm" />
                                </div>
                            </td>
                        </tr>
                    @endforeach

                    {{-- Телефон: те же объекты компактной панелью. --}}
                    @if ($classObjects !== [])
                        <tr class="md-tree-table__detail md-classes-mobile-objects"
                            x-show="visibleObjects({{ $classId }}).length > 0" x-cloak>
                            <td colspan="5">
                                <div class="md-tree-table__panel" style="--md-tree-level: {{ $depth }}">
                                    <div class="md-tree-table__panel-head">
                                        <span>Объекты класса</span>
                                        <span class="md-badge md-badge--outline">{{ $objectTotal }}</span>
                                    </div>
                                    <div class="md-classes-object-list">
                                        @foreach ($classObjects as $classObject)
                                            <a class="md-classes-object-link"
                                               href="/objects/{{ (int)$classObject['id'] }}/edit"
                                               x-show="isObjectRowVisible({{ $classId }}, {{ (int)$classObject['id'] }})" x-cloak>
                                                <i class="ti ti-box" aria-hidden="true"></i>
                                                <strong>{{ $classObject['title'] }}</strong>
                                                @if (($classObject['location_title'] ?? '') !== '')
                                                    <span class="md-classes-object-link__location">
                                                        <i class="ti ti-map-pin" aria-hidden="true"></i>{{ $classObject['location_title'] }}
                                                    </span>
                                                @endif
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
        </x-md.table>

        <div class="md-empty" x-show="active && shownCount === 0" x-cloak>
            <span class="md-empty__icon"><i class="ti ti-search" aria-hidden="true"></i></span>
            <div class="md-empty__title">Ничего не найдено</div>
            <p class="md-empty__text">Измените запрос или сбросьте фильтр.</p>
            <div class="md-empty__actions">
                <button type="button" class="md-btn md-btn--secondary md-btn--sm" @click="reset()">Сбросить фильтры</button>
            </div>
        </div>
    @endif
</div>
