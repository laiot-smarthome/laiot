@php
    $isNew = $class === null;
    $classId = (int)($class['id'] ?? 0);
    $field = static fn (string $key): string => (string)($draft[$key] ?? $class[$key] ?? '');
    $title = $field('title');
    $description = $field('description');
    $path = (string)($path ?? '');
    $propertyTotal = count($properties);
    $methodTotal = count($methods);
    $objectTotal = count($objects);
    $ownPropertyRows = array_values(array_filter($properties, static fn (array $row): bool => $row['origin'] === 'own'));
    $inheritedPropertyRows = array_values(array_filter($properties, static fn (array $row): bool => $row['origin'] === 'inherited'));
    $ownMethodRows = array_values(array_filter($methods, static fn (array $row): bool => $row['origin'] === 'own'));
    $inheritedMethodRows = array_values(array_filter($methods, static fn (array $row): bool => $row['origin'] === 'inherited'));
    $tabs = $isNew ? [] : [
        ['id' => 'overview', 'label' => 'Основное', 'icon' => 'info-circle', 'href' => '/classes/' . $classId . '/edit'],
        ['id' => 'properties', 'label' => 'Свойства', 'icon' => 'adjustments', 'count' => $propertyTotal, 'href' => '/classes/' . $classId . '/edit?tab=properties'],
        ['id' => 'methods', 'label' => 'Методы', 'icon' => 'braces', 'count' => $methodTotal, 'href' => '/classes/' . $classId . '/edit?tab=methods'],
        ['id' => 'objects', 'label' => 'Объекты', 'icon' => 'box', 'count' => $objectTotal, 'href' => '/classes/' . $classId . '/edit?tab=objects'],
    ];
    $parentTitle = '';
    foreach ($classes as $row) {
        if ((int)$row['id'] === $parentId) {
            $parentTitle = (string)$row['title'];
            break;
        }
    }
    $lede = $isNew
        ? 'Класс объединяет объекты с одинаковыми свойствами и методами. Имя — латиницей, описание — для людей.'
        : ($description !== '' ? $description : ($path !== '' ? $path : 'Свойства и методы класса наследуют все его объекты, включая объекты подклассов.'));
@endphp

<div class="md-ui-page md-ui-page--stack md-classes-page">
    <x-md.page-hero
        :eyebrow="$isNew ? 'Новый класс' : 'Карточка класса'"
        icon="hierarchy"
        :title="$isNew ? 'Новый класс' : $class['title']"
        :lede="$lede"
        :meta="$isNew ? [] : [
            ['icon' => 'git-branch', 'label' => 'Подклассов', 'value' => count($children)],
            ['icon' => 'adjustments', 'label' => 'Свойств', 'value' => $propertyTotal],
            ['icon' => 'braces', 'label' => 'Методов', 'value' => $methodTotal],
            ['icon' => 'box', 'label' => 'Объектов', 'value' => $objectTotal],
        ]">
        <x-slot:actions>
            <x-md.button href="/classes" variant="secondary" icon="arrow-left">К классам</x-md.button>
            @if (!$isNew)
                <x-md.button href="/classes/create?parent_id={{ $classId }}" variant="secondary" icon="git-branch">Подкласс</x-md.button>
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

            <x-md.card title="Основное"
                       subtitle="Системное имя, родитель и назначение класса в структуре дома.">
                <div class="md-ui-form-stack">
                    <div class="md-ui-form-grid">
                        <x-md.select name="parent_id" label="Родительский класс" :selected="$parentId"
                                     hint="Подкласс наследует свойства и методы родителя.">
                            <option value="0">— нет —</option>
                            @foreach ($parentOptions as $option)
                                <option value="{{ $option['id'] }}" @selected($parentId === (int)$option['id'])>{{ $option['title'] }}</option>
                            @endforeach
                        </x-md.select>

                        <x-md.name-field name="title" label="Системное имя" :value="$title" :taken="$takenNames"
                                         required
                                         hint="Латиница, цифры и _; например Rooms или OperationalModes." />
                    </div>

                    <x-md.textarea name="description" label="Описание" :value="$description" rows="3"
                                   hint="Коротко опишите, какие объекты описывает класс и что он умеет." />

                    @if ($parentTitle !== '')
                        <x-md.hint tone="info" title="Наследование включено"
                                   :text="'Свойства и методы класса «' . $parentTitle . '» действуют у этого класса и всех его объектов.'" />
                    @endif
                </div>

                <div class="md-ui-form-actions">
                    <x-md.button type="submit" icon="check">{{ $isNew ? 'Создать класс' : 'Сохранить' }}</x-md.button>
                    <x-md.button href="/classes" variant="ghost">Отмена</x-md.button>
                </div>
            </x-md.card>
        </form>

        @if (!$isNew)
            <x-md.card title="Дочерние классы"
                       subtitle="Классы, которые наследуют свойства и методы текущего.">
                <x-slot:actions>
                    <x-md.button href="/classes/create?parent_id={{ $classId }}" icon="plus" size="sm">Добавить подкласс</x-md.button>
                </x-slot:actions>

                @if ($children === [])
                    <x-md.empty-state icon="git-branch" title="Подклассов нет"
                                      text="Подкласс нужен, когда объекты похожи, но добавляют свои свойства или методы." />
                @else
                    <div class="md-tree-objects">
                        @foreach ($children as $child)
                            <a class="md-tree-object" href="/classes/{{ $child['id'] }}/edit">
                                <span class="md-tree-object__icon"><i class="ti ti-hierarchy-2" aria-hidden="true"></i></span>
                                <span class="md-tree-object__body">
                                    <strong>{{ $child['title'] }}</strong>
                                    @if (($child['description'] ?? '') !== '')
                                        <small>{{ $child['description'] }}</small>
                                    @else
                                        <small>Подкласс класса {{ $class['title'] }}</small>
                                    @endif
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-md.card>
        @endif
    @elseif ($tab === 'properties')
        <x-md.card title="Свои свойства" :subtitle="'Своих: ' . $ownProperties . ' · унаследовано: ' . $inheritedProperties">
            <x-slot:actions>
                <x-md.button href="/properties/create?class_id={{ $classId }}" icon="plus" size="sm">Добавить свойство</x-md.button>
            </x-slot:actions>

            @if ($ownPropertyRows === [])
                <x-md.empty-state icon="adjustments" title="Своих свойств нет"
                                  text="Добавьте свойство класса: тип, историю и OnChange. Значения появятся у объектов." />
            @else
                <x-md.table label="Свойства класса">
                    <x-slot:head>
                        <th scope="col">Свойство</th>
                        <th scope="col">Тип</th>
                        <th scope="col">История</th>
                        <th scope="col">OnChange</th>
                        <th scope="col">Привязки</th>
                        <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach ($ownPropertyRows as $property)
                            <tr>
                                <td>
                                    <div class="md-table__name">
                                        <span class="md-table__icon"><i class="ti ti-list-check" aria-hidden="true"></i></span>
                                        <div>
                                            <a href="/properties/{{ $property['id'] }}/edit"><strong>{{ $property['title'] }}</strong></a>
                                            @if ($property['description'] !== '')
                                                <small>{{ $property['description'] }}</small>
                                            @else
                                                <small>Без описания</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
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
                                    @forelse ($propertyBindings[$property['id']] ?? [] as $binding)
                                        <x-md.badge tone="outline">{{ $binding['module'] }}</x-md.badge>
                                    @empty
                                        <span class="md-table__muted">—</span>
                                    @endforelse
                                </td>
                                <td>
                                    <div class="md-table__actions">
                                        <x-md.icon-button href="/properties/{{ $property['id'] }}/edit" icon="pencil"
                                                          label="Открыть свойство" size="sm" />
                                        <form method="post" action="/properties/{{ $property['id'] }}/delete"
                                              data-md-confirm="Удалить свойство «{{ $property['title'] }}»? Значения и история останутся в БД.">
                                            <input type="hidden" name="_token" value="{{ $csrf }}">
                                            <x-md.icon-button type="submit" icon="trash" label="Удалить свойство" variant="danger" size="sm" />
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-md.table>
            @endif
        </x-md.card>

        @if ($inheritedPropertyRows !== [])
            <x-md.card title="Унаследованные свойства"
                       subtitle="Действуют у класса и его объектов, но редактируются в родительском классе.">
                <x-md.table label="Унаследованные свойства">
                    <x-slot:head>
                        <th scope="col">Свойство</th>
                        <th scope="col">Тип</th>
                        <th scope="col">Класс</th>
                        <th scope="col">Привязки</th>
                        <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach ($inheritedPropertyRows as $property)
                            <tr>
                                <td><strong>{{ $property['title'] }}</strong></td>
                                <td><x-md.badge tone="outline">{{ $property['data_type'] }}</x-md.badge></td>
                                <td>
                                    <a class="md-badge md-badge--info" href="/classes/{{ $property['owner_class_id'] }}/edit?tab=properties">
                                        {{ $property['owner_class_title'] }}
                                    </a>
                                </td>
                                <td>
                                    @forelse ($propertyBindings[$property['id']] ?? [] as $binding)
                                        <x-md.badge tone="outline">{{ $binding['module'] }}</x-md.badge>
                                    @empty
                                        <span class="md-table__muted">—</span>
                                    @endforelse
                                </td>
                                <td>
                                    <div class="md-table__actions">
                                        <x-md.icon-button href="/classes/{{ $property['owner_class_id'] }}/edit?tab=properties"
                                                          icon="arrow-up-right" label="Открыть в родительском классе" size="sm" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-md.table>
            </x-md.card>
        @endif
    @elseif ($tab === 'methods')
        <x-md.card title="Свои методы" :subtitle="'Своих: ' . $ownMethods . ' · унаследовано: ' . $inheritedMethods">
            <x-slot:actions>
                <x-md.button href="/methods/create?class_id={{ $classId }}" icon="plus" size="sm">Добавить метод</x-md.button>
            </x-slot:actions>

            @if ($ownMethodRows === [])
                <x-md.empty-state icon="braces" title="Своих методов нет"
                                  text="Метод — PHP-код класса. Подклассы и их объекты наследуют его без копирования." />
            @else
                <x-md.table label="Методы класса">
                    <x-slot:head>
                        <th scope="col">Метод</th>
                        <th scope="col">Описание</th>
                        <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach ($ownMethodRows as $method)
                            <tr>
                                <td>
                                    <div class="md-table__name">
                                        <span class="md-table__icon"><i class="ti ti-braces" aria-hidden="true"></i></span>
                                        <div>
                                            <a href="/methods/{{ $method['id'] }}/edit"><strong>{{ $method['title'] }}</strong></a>
                                            <small>{{ $method['description'] !== '' ? $method['description'] : 'Без описания' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $method['description'] !== '' ? $method['description'] : '—' }}</td>
                                <td>
                                    <div class="md-table__actions">
                                        <x-md.icon-button href="/methods/{{ $method['id'] }}/edit" icon="pencil"
                                                          label="Открыть метод" size="sm" />
                                        <form method="post" action="/methods/{{ $method['id'] }}/delete"
                                              data-md-confirm="Удалить метод «{{ $method['title'] }}»?">
                                            <input type="hidden" name="_token" value="{{ $csrf }}">
                                            <x-md.icon-button type="submit" icon="trash" label="Удалить метод" variant="danger" size="sm" />
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-md.table>
            @endif
        </x-md.card>

        @if ($inheritedMethodRows !== [])
            <x-md.card title="Унаследованные методы"
                       subtitle="Действуют у класса и его объектов, но редактируются в родительском классе.">
                <x-md.table label="Унаследованные методы">
                    <x-slot:head>
                        <th scope="col">Метод</th>
                        <th scope="col">Описание</th>
                        <th scope="col">Класс</th>
                        <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach ($inheritedMethodRows as $method)
                            <tr>
                                <td><strong>{{ $method['title'] }}</strong></td>
                                <td>{{ $method['description'] !== '' ? $method['description'] : '—' }}</td>
                                <td>
                                    <a class="md-badge md-badge--info" href="/classes/{{ $method['owner_class_id'] }}/edit?tab=methods">
                                        {{ $method['owner_class_title'] }}
                                    </a>
                                </td>
                                <td>
                                    <div class="md-table__actions">
                                        <x-md.icon-button href="/classes/{{ $method['owner_class_id'] }}/edit?tab=methods"
                                                          icon="arrow-up-right" label="Открыть в родительском классе" size="sm" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-md.table>
            </x-md.card>
        @endif
    @elseif ($tab === 'objects')
        <x-md.card title="Объекты класса" :subtitle="'Объектов: ' . $objectTotal . ' · локация задаётся у объекта'">
            <x-slot:actions>
                <x-md.button href="/objects/create?class_id={{ $classId }}" icon="plus" size="sm">Добавить объект</x-md.button>
            </x-slot:actions>

            @if ($objects === [])
                <x-md.empty-state icon="box" title="Объектов нет"
                                  text="Объект — конкретное устройство или помещение этого класса. Значения свойств и расположение хранятся у объекта." />
            @else
                <x-md.table label="Объекты класса">
                    <x-slot:head>
                        <th scope="col">Объект</th>
                        <th scope="col">Расположение</th>
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
                                    @if (($classObject['location_title'] ?? '') !== '')
                                        <span class="md-badge md-badge--outline">
                                            <i class="ti ti-map-pin" aria-hidden="true"></i>{{ $classObject['location_title'] }}
                                        </span>
                                    @else
                                        <span class="md-table__muted">Не указано</span>
                                    @endif
                                </td>
                                <td>{{ $classObject['description'] !== '' ? $classObject['description'] : '—' }}</td>
                                <td>
                                    <div class="md-table__actions">
                                        <x-md.icon-button href="/objects/{{ $classObject['id'] }}/edit?tab=properties"
                                                          icon="adjustments" label="Свойства объекта" size="sm" />
                                        <x-md.icon-button href="/objects/{{ $classObject['id'] }}/edit"
                                                          icon="pencil" label="Открыть объект" size="sm" />
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
