@php
    $isNew = $class === null;
    $classId = (int)($class['id'] ?? 0);
    $title = (string)($draft['title'] ?? $class['title'] ?? '');
    $tabs = $isNew ? [] : [
        ['id' => '', 'label' => 'Основное', 'icon' => 'info-circle', 'href' => '/classes/' . $classId . '/edit'],
        ['id' => 'properties', 'label' => 'Свойства', 'icon' => 'adjustments', 'count' => count($properties), 'href' => '/classes/' . $classId . '/edit?tab=properties'],
        ['id' => 'methods', 'label' => 'Методы', 'icon' => 'code', 'count' => count($methods), 'href' => '/classes/' . $classId . '/edit?tab=methods'],
        ['id' => 'objects', 'label' => 'Объекты', 'icon' => 'box', 'count' => count($objects), 'href' => '/classes/' . $classId . '/edit?tab=objects'],
    ];
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        :eyebrow="$isNew ? 'Новый класс' : 'Карточка класса'"
        icon="hierarchy"
        :title="$isNew ? 'Новый класс' : $class['title']"
        :lede="$isNew ? 'Класс объединяет объекты с одинаковым набором свойств и методов.' : 'Свойства и методы класса наследуют все его объекты.'"
        :meta="$isNew ? [] : [
            ['icon' => 'adjustments', 'label' => 'Свойств', 'value' => count($properties)],
            ['icon' => 'code', 'label' => 'Методов', 'value' => count($methods)],
            ['icon' => 'box', 'label' => 'Объектов', 'value' => count($objects)],
        ]">
        <x-slot:actions>
            <x-md.button href="/classes" variant="secondary" icon="arrow-left">К классам</x-md.button>
            @if (!$isNew)
                <form method="post" action="/classes/{{ $classId }}/delete" class="md-inline-form"
                      data-md-confirm="Удалить класс «{{ $class['title'] }}»?">
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

    @if ($tab === '')
        <form method="post" action="{{ $isNew ? '/classes/create' : '/classes/' . $classId . '/edit' }}" class="md-ui-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">

            <x-md.card title="Название класса">
                <div class="md-ui-form-stack">
                    <x-md.input name="title" label="Название" :value="$title" required
                                hint="Используется в объектах и сценариях, например «Датчик температуры»." />
                </div>
            </x-md.card>

            <div class="md-ui-form-actions">
                <x-md.button type="submit" icon="check">{{ $isNew ? 'Создать класс' : 'Сохранить' }}</x-md.button>
                <x-md.button href="/classes" variant="ghost">Отмена</x-md.button>
            </div>
        </form>
    @elseif ($tab === 'properties')
        <x-md.card title="Свойства класса" :subtitle="'Всего: ' . count($properties)">
            <div class="md-ui-actions">
                <x-md.button href="/properties/create?class_id={{ $classId }}" icon="plus" size="sm">Добавить свойство</x-md.button>
            </div>
            @if ($properties === [])
                <x-md.empty-state icon="adjustments" title="Свойств нет"
                                  text="Добавьте свойство в общем разделе «Свойства», выбрав этот класс." />
            @else
                <x-md.table label="Свойства класса">
                    <x-slot:head>
                        <th scope="col">Свойство</th>
                        <th scope="col">Тип</th>
                        <th scope="col">История</th>
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
                                    <div class="md-table__actions">
                                        <x-md.icon-button href="/properties/{{ $property['id'] }}/edit" icon="pencil" label="Открыть свойство" size="sm" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-md.table>
            @endif
        </x-md.card>
    @elseif ($tab === 'methods')
        <x-md.card title="Методы класса" :subtitle="'Всего: ' . count($methods)">
            <div class="md-ui-actions">
                <x-md.button href="/methods/create?class_id={{ $classId }}" icon="plus" size="sm">Добавить метод</x-md.button>
            </div>
            @if ($methods === [])
                <x-md.empty-state icon="code" title="Методов нет"
                                  text="Добавьте метод в общем разделе «Методы», выбрав этот класс." />
            @else
                <x-md.table label="Методы класса">
                    <x-slot:head>
                        <th scope="col">Метод</th>
                        <th scope="col">Описание</th>
                        <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach ($methods as $method)
                            <tr>
                                <td><strong>{{ $method['title'] }}</strong></td>
                                <td>{{ $method['description'] !== '' ? $method['description'] : '—' }}</td>
                                <td>
                                    <div class="md-table__actions">
                                        <x-md.icon-button href="/methods/{{ $method['id'] }}/edit" icon="pencil" label="Открыть метод" size="sm" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-md.table>
            @endif
        </x-md.card>
    @elseif ($tab === 'objects')
        <x-md.card title="Объекты класса" :subtitle="'Всего: ' . count($objects)">
            <div class="md-ui-actions">
                <x-md.button href="/objects/create?class_id={{ $classId }}" icon="plus" size="sm">Добавить объект</x-md.button>
                <x-md.button href="/objects?class_id={{ $classId }}" variant="secondary" size="sm" icon="box">Все объекты класса</x-md.button>
            </div>
            @if ($objects === [])
                <x-md.empty-state icon="box" title="Объектов нет"
                                  text="Объекты создаются в разделе «Объекты»: выберите этот класс в карточке объекта." />
            @else
                <div class="md-tree-objects">
                    @foreach ($objects as $classObject)
                        <a class="md-tree-object" href="/objects/{{ $classObject['id'] }}/edit">
                            <span class="md-tree-object__icon"><i class="ti ti-box" aria-hidden="true"></i></span>
                            <span class="md-tree-object__body"><strong>{{ $classObject['title'] }}</strong></span>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-md.card>
    @endif
</div>
