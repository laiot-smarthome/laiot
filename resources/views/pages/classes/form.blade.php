@php
    $isNew = $class === null;
    $classId = (int)($class['id'] ?? 0);
    $title = (string)($draft['title'] ?? $class['title'] ?? '');
    $propertyTotal = count($properties);
    $methodTotal = count($methods);
    $objectTotal = count($objects);
    $tabs = $isNew ? [] : [
        ['id' => 'overview', 'label' => 'Основное', 'icon' => 'info-circle', 'href' => '/classes/' . $classId . '/edit'],
        ['id' => 'properties', 'label' => 'Свойства', 'icon' => 'adjustments', 'count' => $propertyTotal, 'href' => '/classes/' . $classId . '/edit?tab=properties'],
        ['id' => 'methods', 'label' => 'Методы', 'icon' => 'code', 'count' => $methodTotal, 'href' => '/classes/' . $classId . '/edit?tab=methods'],
        ['id' => 'objects', 'label' => 'Объекты', 'icon' => 'box', 'count' => $objectTotal, 'href' => '/classes/' . $classId . '/edit?tab=objects'],
    ];
    $parentTitle = '';
    foreach ($classes as $row) {
        if ((int)$row['id'] === $parentId) {
            $parentTitle = (string)$row['title'];
            break;
        }
    }
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        :eyebrow="$isNew ? 'Новый класс' : 'Карточка класса'"
        icon="hierarchy"
        :title="$isNew ? 'Новый класс' : $class['title']"
        :lede="$isNew
            ? 'Класс объединяет объекты с одинаковыми свойствами и методами. Подкласс наследует их и может добавить свои.'
            : ($path !== '' ? $path : 'Свойства и методы класса наследуют все его объекты, включая объекты подклассов.')"
        :meta="$isNew ? [] : [
            ['icon' => 'git-branch', 'label' => 'Подклассов', 'value' => count($children)],
            ['icon' => 'adjustments', 'label' => 'Свойств', 'value' => $propertyTotal],
            ['icon' => 'code', 'label' => 'Методов', 'value' => $methodTotal],
            ['icon' => 'box', 'label' => 'Объектов', 'value' => $objectTotal],
        ]">
        <x-slot:actions>
            <x-md.button href="/classes" variant="secondary" icon="arrow-left">К классам</x-md.button>
            @if (!$isNew)
                <form method="post" action="/classes/{{ $classId }}/delete" class="md-inline-form"
                      data-md-confirm="Удалить класс «{{ $class['title'] }}»? Удалить можно только класс без подклассов и объектов.">
                    <input type="hidden" name="_token" value="{{ $csrf }}">
                    <x-md.button type="submit" variant="danger" icon="trash">Удалить</x-md.button>
                </form>
            @endif
        </x-slot:actions>
    </x-md.page-hero>

    @if ($error !== null)
        <x-md.alert type="danger" title="Не удалось сохранить класс" :text="$error" />
    @endif

    @if (!$isNew)
        <x-md.tabs :items="$tabs" :active="$tab" label="Разделы класса" />
    @endif

    @if ($tab === 'overview')
        <form method="post" action="{{ $isNew ? '/classes/create' : '/classes/' . $classId . '/edit' }}" class="md-ui-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">

            <x-md.card title="Название и наследование">
                <div class="md-ui-form-stack">
                    <div class="md-ui-form-grid">
                        <x-md.input name="title" label="Название" :value="$title" required
                                    hint="Например «Датчик температуры» или «Свет»." />
                        <x-md.select name="parent_id" label="Родительский класс" :selected="$parentId"
                                     hint="Подкласс наследует свойства и методы родителя и может их переопределить.">
                            <option value="0">Без родительского класса</option>
                            @foreach ($parentOptions as $option)
                                <option value="{{ $option['id'] }}" @selected($parentId === (int)$option['id'])>{{ $option['title'] }}</option>
                            @endforeach
                        </x-md.select>
                    </div>
                    @if ($parentTitle !== '')
                        <x-md.hint tone="info" title="Наследование включено"
                                   :text="'Свойства и методы класса «' . $parentTitle . '» действуют у этого класса и всех его объектов.'" />
                    @endif
                </div>
            </x-md.card>

            <div class="md-ui-form-actions">
                <x-md.button type="submit" icon="check">{{ $isNew ? 'Создать класс' : 'Сохранить' }}</x-md.button>
                <x-md.button href="/classes" variant="ghost">Отмена</x-md.button>
            </div>
        </form>

        @if (!$isNew)
            <x-md.card title="Иерархия" :subtitle="$path">
                <div class="md-ui-form-stack">
                    <div class="md-ui-actions">
                        <x-md.button href="/classes/create?parent_id={{ $classId }}" icon="git-branch" size="sm">Добавить подкласс</x-md.button>
                        <x-md.button href="/classes/{{ $classId }}/edit?tab=properties" variant="secondary" icon="adjustments" size="sm">Свойства</x-md.button>
                        <x-md.button href="/classes/{{ $classId }}/edit?tab=objects" variant="secondary" icon="box" size="sm">Объекты</x-md.button>
                    </div>

                    @if ($children === [])
                        <x-md.empty-state icon="git-branch" title="Подклассов нет"
                                          text="Подкласс нужен, когда объекты похожи, но добавляют свои свойства или методы." />
                    @else
                        <div class="md-tree-objects">
                            @foreach ($children as $child)
                                <a class="md-tree-object" href="/classes/{{ $child['id'] }}/edit">
                                    <span class="md-tree-object__icon"><i class="ti ti-hierarchy-3" aria-hidden="true"></i></span>
                                    <span class="md-tree-object__body">
                                        <strong>{{ $child['title'] }}</strong>
                                        <small>Подкласс класса {{ $class['title'] }}</small>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-md.card>
        @endif
    @elseif ($tab === 'properties')
        <x-md.card title="Свойства класса"
                   :subtitle="'Своих: ' . $ownProperties . ' · унаследовано: ' . $inheritedProperties">
            <div class="md-ui-actions">
                <x-md.button href="/properties/create?class_id={{ $classId }}" icon="plus" size="sm">Добавить свойство</x-md.button>
            </div>
            @if ($properties === [])
                <x-md.empty-state icon="adjustments" title="Свойств нет"
                                  text="Добавьте свойство класса: тип, историю и OnChange. Значения появятся у объектов." />
            @else
                <x-md.table label="Свойства класса">
                    <x-slot:head>
                        <th scope="col">Свойство</th>
                        <th scope="col">Тип</th>
                        <th scope="col">История</th>
                        <th scope="col">OnChange</th>
                        <th scope="col">Источник</th>
                        <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach ($properties as $property)
                            <tr>
                                <td><strong>{{ $property['title'] }}</strong></td>
                                <td><x-md.badge tone="outline">{{ $property['data_type'] }}</x-md.badge></td>
                                <td>
                                    @if (!empty($property['keep_history']))
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
                                    @if ($property['origin'] === 'own')
                                        <x-md.badge tone="primary">своё</x-md.badge>
                                    @else
                                        <a href="/classes/{{ $property['owner_class_id'] }}/edit?tab=properties">
                                            <x-md.badge tone="info">от {{ $property['owner_class_title'] }}</x-md.badge>
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    <div class="md-table__actions">
                                        @if ($property['origin'] === 'own')
                                            <x-md.icon-button href="/properties/{{ $property['id'] }}/edit" icon="pencil" label="Открыть свойство" size="sm" />
                                        @else
                                            <x-md.icon-button href="/classes/{{ $property['owner_class_id'] }}/edit?tab=properties" icon="arrow-up-right" label="Открыть в родительском классе" size="sm" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-md.table>
            @endif
        </x-md.card>
    @elseif ($tab === 'methods')
        <x-md.card title="Методы класса"
                   :subtitle="'Своих: ' . $ownMethods . ' · унаследовано: ' . $inheritedMethods">
            <div class="md-ui-actions">
                <x-md.button href="/methods/create?class_id={{ $classId }}" icon="plus" size="sm">Добавить метод</x-md.button>
            </div>
            @if ($methods === [])
                <x-md.empty-state icon="code" title="Методов нет"
                                  text="Метод — PHP-код класса. Подклассы и их объекты наследуют его без копирования." />
            @else
                <x-md.table label="Методы класса">
                    <x-slot:head>
                        <th scope="col">Метод</th>
                        <th scope="col">Описание</th>
                        <th scope="col">Источник</th>
                        <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach ($methods as $method)
                            <tr>
                                <td><strong>{{ $method['title'] }}</strong></td>
                                <td>{{ $method['description'] !== '' ? $method['description'] : '—' }}</td>
                                <td>
                                    @if ($method['origin'] === 'own')
                                        <x-md.badge tone="primary">свой</x-md.badge>
                                    @else
                                        <a href="/classes/{{ $method['owner_class_id'] }}/edit?tab=methods">
                                            <x-md.badge tone="info">от {{ $method['owner_class_title'] }}</x-md.badge>
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    <div class="md-table__actions">
                                        @if ($method['origin'] === 'own')
                                            <x-md.icon-button href="/methods/{{ $method['id'] }}/edit" icon="pencil" label="Открыть метод" size="sm" />
                                        @else
                                            <x-md.icon-button href="/classes/{{ $method['owner_class_id'] }}/edit?tab=methods" icon="arrow-up-right" label="Открыть в родительском классе" size="sm" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-md.table>
            @endif
        </x-md.card>
    @elseif ($tab === 'objects')
        <x-md.card title="Объекты класса"
                   :subtitle="'Всего: ' . $objectTotal . ' (включая объекты подклассов)'">
            <div class="md-ui-actions">
                <x-md.button href="/objects/create?class_id={{ $classId }}" icon="plus" size="sm">Добавить объект</x-md.button>
            </div>
            @if ($objects === [])
                <x-md.empty-state icon="box" title="Объектов нет"
                                  text="Объект — конкретное устройство или помещение этого класса. Значения свойств хранятся у объекта." />
            @else
                <x-md.table label="Объекты класса">
                    <x-slot:head>
                        <th scope="col">Объект</th>
                        <th scope="col">Класс</th>
                        <th scope="col">Описание</th>
                        <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach ($objects as $classObject)
                            <tr>
                                <td>
                                    <div class="md-table__name">
                                        <span class="md-table__icon"><i class="ti ti-box" aria-hidden="true"></i></span>
                                        <div><a href="/objects/{{ $classObject['id'] }}/edit"><strong>{{ $classObject['title'] }}</strong></a></div>
                                    </div>
                                </td>
                                <td>
                                    @if ($classObject['class_title'] !== '')
                                        <x-md.badge tone="outline">{{ $classObject['class_title'] }}</x-md.badge>
                                    @else
                                        <span class="md-table__muted">Без класса</span>
                                    @endif
                                </td>
                                <td>{{ $classObject['description'] !== '' ? $classObject['description'] : '—' }}</td>
                                <td>
                                    <div class="md-table__actions">
                                        <x-md.icon-button href="/objects/{{ $classObject['id'] }}/edit" icon="pencil" label="Открыть объект" size="sm" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-md.table>
            @endif
        </x-md.card>
    @endif
</div>
