@php
    $isNew = $object === null;
    $field = static fn (string $key): string => (string)($draft[$key] ?? $object[$key] ?? '');
    $classId = (int)($draft['class_id'] ?? $object['class_id'] ?? 0);
    $locationId = (int)($draft['location_id'] ?? $object['location_id'] ?? 0);
    $objectId = (int)($object['id'] ?? 0);
    $className = (string)($class['title'] ?? '');
    $locationNames = [];
    foreach ($locations as $location) {
        $locationNames[(int)$location['id']] = (string)$location['title'];
    }
    $formatTime = static fn (?string $value): string => ($value === null || $value === '' || strlen($value) < 16)
        ? '—'
        : substr($value, 8, 2) . '.' . substr($value, 5, 2) . ' · ' . substr($value, 11, 5);
    $tabs = $isNew ? [] : [
        ['id' => 'details', 'label' => 'Основное', 'icon' => 'info-circle', 'href' => '/objects/' . $objectId . '/edit'],
        ['id' => 'properties', 'label' => 'Свойства и значения', 'icon' => 'adjustments', 'count' => count($properties), 'href' => '/objects/' . $objectId . '/edit?tab=properties'],
        ['id' => 'methods', 'label' => 'Методы', 'icon' => 'braces', 'count' => count($classMethods), 'href' => '/objects/' . $objectId . '/edit?tab=methods'],
        ['id' => 'history', 'label' => 'История', 'icon' => 'history', 'href' => '/objects/' . $objectId . '/edit?tab=history'],
        ['id' => 'journal', 'label' => 'Журнал', 'icon' => 'bolt', 'href' => '/objects/' . $objectId . '/edit?tab=journal'],
    ];
    $statusLabels = [
        'changed' => 'Изменено',
        'stopped' => 'Цикл остановлен',
        'running' => 'Выполняется',
        'ok' => 'Обработчик выполнен',
        'error' => 'Ошибка',
        'missing' => 'Метод не найден',
        'skipped' => 'Пропущено',
    ];
    $backUrl = $classId > 0 ? '/classes/' . $classId . '/edit?tab=objects' : '/classes';
    $locationName = $locationNames[$locationId] ?? 'Не указано';
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        :eyebrow="$isNew ? 'Новый объект' : ($classPath !== '' ? $classPath : 'Объект без класса')"
        icon="box"
        :title="$isNew ? 'Новый объект' : $object['title']"
        :lede="$isNew
            ? 'Объект — конкретное устройство или помещение. Класс задаёт свойства и методы, расположение — помещение объекта.'
            : ($object['description'] !== '' ? $object['description'] : 'Описание не задано.')"
        :meta="$isNew ? [] : [
            ['icon' => 'hierarchy', 'label' => 'Класс', 'value' => $className !== '' ? $className : 'не задан'],
            ['icon' => 'map-pin', 'label' => 'Расположение', 'value' => $locationName],
            ['icon' => 'adjustments', 'label' => 'Свойств', 'value' => count($properties)],
            ['icon' => 'braces', 'label' => 'Методов', 'value' => count($classMethods)],
        ]">
        <x-slot:actions>
            <x-md.button :href="$backUrl" variant="secondary" icon="arrow-left">К классу</x-md.button>
            @if (!$isNew)
                <form method="post" action="/objects/{{ $objectId }}/delete" class="md-inline-form"
                      data-md-confirm="Удалить объект «{{ $object['title'] }}»? Записи истории останутся в БД.">
                    <input type="hidden" name="_token" value="{{ $csrf }}">
                    <x-md.button type="submit" variant="danger" icon="trash">Удалить</x-md.button>
                </form>
            @endif
        </x-slot:actions>
    </x-md.page-hero>

    @if ($error !== null)
        <x-md.notify type="danger" title="Не удалось сохранить объект" :text="$error" />
    @endif

    @if (!$isNew)
        <x-md.tabs :items="$tabs" :active="$tab" label="Разделы объекта" />
    @endif

    @if ($tab === 'details')
        <form method="post" action="{{ $isNew ? '/objects/create' : '/objects/' . $objectId . '/edit' }}" class="md-ui-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">

            <x-md.card title="Данные объекта"
                       subtitle="Название, класс и расположение. Свойства и методы доступны на соседних вкладках.">
                <div class="md-ui-form-stack">
                    <div class="md-ui-form-grid">
                        <x-md.name-field name="title" label="Системное имя" :value="$field('title')"
                                         :taken="$takenNames" required
                                         hint="Латиница, цифры и _; например KitchenLight." />

                        <x-md.select name="class_id" label="Класс" :selected="$classId" required
                                     hint="Класс задаёт свойства и методы объекта.">
                            <option value="">Выберите класс</option>
                            @foreach ($classes as $row)
                                <option value="{{ $row['id'] }}" @selected($classId === (int)$row['id'])>{{ $row['title'] }}</option>
                            @endforeach
                        </x-md.select>
                    </div>

                    <x-md.select name="location_id" label="Расположение" :selected="$locationId"
                                 hint="Локация принадлежит объекту, а не классу: один класс может быть в разных помещениях.">
                        <option value="0">Не указано</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location['id'] }}" @selected($locationId === (int)$location['id'])>{{ $location['title'] }}</option>
                        @endforeach
                    </x-md.select>

                    <x-md.textarea name="description" label="Описание" :value="$field('description')" rows="3"
                                   hint="Например: датчик движения в прихожей." />

                    @if ($isNew)
                        <x-md.hint tone="info" title="Значения появятся после сохранения"
                                   text="Сохраните объект — затем задайте значения свойств на вкладке «Свойства и значения»." />
                    @endif
                </div>

                <div class="md-ui-form-actions">
                    <x-md.button type="submit" icon="check">{{ $isNew ? 'Создать объект' : 'Сохранить' }}</x-md.button>
                    <x-md.button :href="$backUrl" variant="ghost">Отмена</x-md.button>
                </div>
            </x-md.card>
        </form>
    @elseif ($tab === 'properties')
        <x-md.card title="Свойства и значения"
                   :subtitle="'Всего: ' . count($properties) . ($objectPropertyCount > 0 ? ' · объектных: ' . $objectPropertyCount : '')">
            <x-slot:actions>
                <x-md.button href="/properties/create?object_id={{ $objectId }}" icon="plus" size="sm">Добавить свойство объекта</x-md.button>
                @if ($classId > 0)
                    <x-md.button href="/classes/{{ $classId }}/edit?tab=properties" variant="secondary" icon="hierarchy" size="sm">Свойства класса</x-md.button>
                @endif
            </x-slot:actions>

            @if ($properties === [])
                <x-md.empty-state icon="adjustments" title="Свойств нет"
                                  text="У класса нет свойств, и объектных пока нет. Добавьте свойство объекта или настройте класс." />
            @else
                <form method="post" action="/objects/{{ $objectId }}/values" class="md-ui-form">
                    <input type="hidden" name="_token" value="{{ $csrf }}">
                    <x-md.table label="Свойства и значения">
                        <x-slot:head>
                            <th scope="col">Свойство</th>
                            <th scope="col">Источник</th>
                            <th scope="col">Тип</th>
                            <th scope="col">Значение</th>
                            <th scope="col">Привязки</th>
                            <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
                        </x-slot:head>
                        <x-slot:body>
                            @foreach ($properties as $property)
                                <tr>
                                    <td><strong>{{ $property['title'] }}</strong></td>
                                    <td>
                                        @if ($property['origin'] === 'object')
                                            <x-md.badge tone="warning">объектное</x-md.badge>
                                        @elseif ($property['origin'] === 'own')
                                            <x-md.badge tone="primary">класс</x-md.badge>
                                        @else
                                            <a href="/classes/{{ $property['owner_class_id'] }}/edit?tab=properties">
                                                <x-md.badge tone="info">от {{ $property['owner_class_title'] }}</x-md.badge>
                                            </a>
                                        @endif
                                    </td>
                                    <td><x-md.badge tone="outline">{{ $property['data_type'] }}</x-md.badge></td>
                                    <td>
                                        <input class="md-input" type="text"
                                               name="values[{{ $property['id'] }}]"
                                               value="{{ $values[$property['id']] ?? '' }}"
                                               aria-label="Значение свойства {{ $property['title'] }}">
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
                                            @if (!empty($property['keep_history']))
                                                @yoyo('property-history', [
                                                    'objectId' => $objectId,
                                                    'propertyId' => $property['id'],
                                                    'title' => $object['title'] . '.' . $property['title'],
                                                ])
                                            @endif
                                            @if ($property['origin'] === 'inherited')
                                                <x-md.icon-button href="/classes/{{ $property['owner_class_id'] }}/edit?tab=properties" icon="arrow-up-right" label="Открыть в родительском классе" size="sm" />
                                            @else
                                                <x-md.icon-button href="/properties/{{ $property['id'] }}/edit" icon="pencil" label="Настроить свойство" size="sm" />
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </x-slot:body>
                    </x-md.table>
                    <div class="md-ui-form-actions">
                        <x-md.button type="submit" icon="check">Сохранить значения</x-md.button>
                    </div>
                </form>
            @endif
        </x-md.card>
    @elseif ($tab === 'methods')
        <x-md.card title="Методы объекта" :subtitle="'Всего: ' . count($classMethods)">
            <x-slot:actions>
                <x-md.button href="/methods/create?object_id={{ $objectId }}" icon="plus" size="sm">Добавить метод объекта</x-md.button>
                @if ($classId > 0)
                    <x-md.button href="/classes/{{ $classId }}/edit?tab=methods" variant="secondary" icon="hierarchy" size="sm">Методы класса</x-md.button>
                @endif
            </x-slot:actions>
            @if ($classMethods === [])
                <x-md.empty-state icon="braces" title="Методов нет"
                                  text="Методы задаются у класса и наследуются объектами. Объектный метод действует только у этого объекта." />
            @else
                <x-md.table label="Методы объекта">
                    <x-slot:head>
                        <th scope="col">Метод</th>
                        <th scope="col">Описание</th>
                        <th scope="col">Источник</th>
                        <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach ($classMethods as $method)
                            <tr>
                                <td><strong>{{ $method['title'] }}</strong></td>
                                <td>{{ $method['description'] !== '' ? $method['description'] : '—' }}</td>
                                <td>
                                    @if ($method['origin'] === 'object')
                                        <x-md.badge tone="warning">объектный</x-md.badge>
                                    @elseif ($method['origin'] === 'own')
                                        <x-md.badge tone="primary">класс</x-md.badge>
                                    @else
                                        <a href="/classes/{{ $method['owner_class_id'] }}/edit?tab=methods">
                                            <x-md.badge tone="info">от {{ $method['owner_class_title'] }}</x-md.badge>
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    <div class="md-table__actions">
                                        @if ($method['origin'] === 'object' || $method['origin'] === 'own')
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
    @elseif ($tab === 'history')
        <x-md.card title="История значений" subtitle="Записи, сохранённые для свойств с включённой историей">
            @if ($history === [])
                <x-md.empty-state icon="history" title="История пуста"
                                  text="Включите «Хранить историю» у свойства и запишите значение — записи появятся здесь." />
            @else
                <x-md.table label="История значений">
                    <x-slot:head>
                        <th scope="col">Когда</th>
                        <th scope="col">Свойство</th>
                        <th scope="col">Значение</th>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach ($history as $row)
                            <tr>
                                <td><span class="md-table__muted">{{ $formatTime($row['created_at']) }}</span></td>
                                <td><code class="md-table__code">{{ $row['property_title'] }}</code></td>
                                <td><span class="md-table__value" title="{{ $row['value'] }}">{{ $row['value'] }}</span></td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-md.table>
            @endif
        </x-md.card>
    @elseif ($tab === 'journal')
        <x-md.card title="Журнал событий" subtitle="Изменения свойств и вызовы OnChange-обработчиков">
            @if ($journal === [])
                <x-md.empty-state icon="bolt" title="Событий пока нет"
                                  text="Журнал заполняется при записи значений через панель, сценарий или API." />
            @else
                <x-md.table label="Журнал событий">
                    <x-slot:head>
                        <th scope="col">Когда</th>
                        <th scope="col">Свойство</th>
                        <th scope="col">Было → стало</th>
                        <th scope="col">Статус</th>
                        <th scope="col">Обработчик</th>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach ($journal as $row)
                            <tr>
                                <td><span class="md-table__muted">{{ $formatTime($row['created_at']) }}</span></td>
                                <td><code class="md-table__code">{{ $row['property_title'] }}</code></td>
                                <td>
                                    <span class="md-table__muted">{{ $row['old_value'] !== '' ? $row['old_value'] : '—' }}</span>
                                    <i class="ti ti-arrow-right" aria-hidden="true"></i>
                                    <span class="md-table__value" title="{{ $row['new_value'] }}">{{ $row['new_value'] !== '' ? $row['new_value'] : '—' }}</span>
                                </td>
                                <td>
                                    <x-md.badge :tone="in_array($row['status'], ['ok', 'changed'], true) ? 'success' : (in_array($row['status'], ['error', 'missing'], true) ? 'danger' : 'warning')">
                                        {{ $statusLabels[$row['status']] ?? $row['status'] }}
                                    </x-md.badge>
                                </td>
                                <td>
                                    @if ($row['handler'] !== '')
                                        <code class="md-table__code">{{ $row['handler'] }}</code>
                                    @else
                                        <span class="md-table__muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-md.table>
            @endif
        </x-md.card>
    @endif
</div>
