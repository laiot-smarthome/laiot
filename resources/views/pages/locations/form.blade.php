@php
    $isNew = $location === null;
    $isRoom = $kind === 'room';
    $kindLabel = $kinds[$kind] ?? $kind;
    $roomObjects = array_values(array_filter(
        $objects,
        static fn (array $row): bool => (int)$row['id'] !== (int)($roomObject['id'] ?? 0)
    ));
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        :eyebrow="$isNew ? 'Новая локация' : 'Карточка локации'"
        icon="map-pin"
        :title="$isNew ? 'Новая локация' : $location['title']"
        :lede="$isNew
            ? 'Дом объединяет этажи, этаж — комнаты. Комната автоматически получает класс Rooms (или подкласс этажа), объект и свойства активности.'
            : ($parent !== null
                ? 'Место: ' . $parent['title'] . '. Тип и родитель задаются при создании.'
                : 'Тип и родитель задаются при создании; здесь меняются название, приоритет и таймаут.')">
        <x-slot:actions>
            <x-md.button href="/locations" variant="secondary" icon="arrow-left">К локациям</x-md.button>
            @if (!$isNew)
                <form method="post" action="/locations/{{ $location['id'] }}/delete" class="md-inline-form"
                      data-md-confirm="Удалить локацию «{{ $location['title'] }}»?">
                    <input type="hidden" name="_token" value="{{ $csrf }}">
                    <x-md.button type="submit" variant="danger" icon="trash">Удалить</x-md.button>
                </form>
            @endif
        </x-slot:actions>
    </x-md.page-hero>

    @if ($error !== null)
        <x-md.alert type="danger" title="Не удалось сохранить локацию" :text="$error" />
    @endif

    <form method="post" action="{{ $isNew ? '/locations/create' : '/locations/' . $location['id'] . '/edit' }}" class="md-ui-form">
        <input type="hidden" name="_token" value="{{ $csrf }}">

        <x-md.card title="Параметры локации">
            <div class="md-ui-form-stack">
                <div class="md-ui-form-grid">
                    <x-md.input name="title" label="Название" :value="$title" required
                                hint="Например: «Дом», «Первый этаж», «Кухня»." />

                    @if ($isNew)
                        <x-md.select name="kind" label="Тип" :selected="$kind"
                                     hint="Комната создаёт объект и класс; этаж — подкласс Rooms.">
                            @foreach ($kinds as $value => $label)
                                <option value="{{ $value }}" @selected($kind === $value)>{{ $label }}</option>
                            @endforeach
                        </x-md.select>
                    @else
                        <div class="md-field">
                            <div class="md-field__label">Тип</div>
                            <x-md.badge tone="neutral">{{ $kindLabel }}</x-md.badge>
                        </div>
                    @endif
                </div>

                <div class="md-ui-form-grid">
                    @if ($isNew)
                        <x-md.select name="parent_id" label="Родительская локация" :selected="(string)$parentId"
                                     hint="Дом — верхний уровень; этаж вкладывается в дом; комната — в этаж или дом.">
                            <option value="0">— верхний уровень —</option>
                            @foreach ($parents as $option)
                                <option value="{{ $option['id'] }}" @selected($parentId === (int)$option['id'])>
                                    {{ str_repeat('— ', (int)$option['depth']) }}{{ $option['title'] }}
                                    ({{ $kinds[$option['kind']] ?? $option['kind'] }})
                                </option>
                            @endforeach
                        </x-md.select>
                    @else
                        <div class="md-field">
                            <div class="md-field__label">Родительская локация</div>
                            @if ($parent !== null)
                                <a href="/locations/{{ $parent['id'] }}/edit">{{ $parent['title'] }}</a>
                            @else
                                <span class="md-ui-muted">верхний уровень</span>
                            @endif
                        </div>
                    @endif

                    <x-md.input name="priority" type="number" label="Приоритет" :value="(string)$priority"
                                hint="Чем больше число, тем выше локация в списках." />
                </div>

                <x-md.input name="activity_timeout" type="number" label="Простой до неактивности, сек"
                            :value="(string)$activityTimeout"
                            :disabled="!$isRoom"
                            hint="Сколько секунд после последнего события роль-свойства комната остаётся активной. Для комнаты по умолчанию 900 секунд." />
            </div>

            <div class="md-ui-form-actions">
                <x-md.button type="submit" icon="check">{{ $isNew ? 'Создать локацию' : 'Сохранить' }}</x-md.button>
                <x-md.button href="/locations" variant="ghost">Отмена</x-md.button>
            </div>
        </x-md.card>
    </form>

    @if (!$isNew && $isRoom)
        <x-md.card title="Модель комнаты"
                   subtitle="Класс и объект созданы автоматически. У объекта хранятся значения активности, у класса — их описания.">
            <div class="md-ui-form-grid">
                <div>
                    <div class="md-field__label">Объект комнаты</div>
                    @if ($roomObject !== null)
                        <a href="/objects/{{ $roomObject['id'] }}/edit"><strong>{{ $roomObject['title'] }}</strong></a>
                        <div class="md-ui-muted">location_id = {{ $location['id'] }}</div>
                    @else
                        <span class="md-ui-muted">не создан</span>
                    @endif
                </div>
                <div>
                    <div class="md-field__label">Класс</div>
                    @if ($roomClass !== null)
                        <a href="/classes/{{ $roomClass['id'] }}/edit"><strong>{{ $roomClass['title'] }}</strong></a>
                        <div class="md-ui-muted">провижининг: Rooms → этаж → объект</div>
                    @else
                        <span class="md-ui-muted">не создан</span>
                    @endif
                </div>
            </div>
        </x-md.card>

        @if ($roomState !== null)
            <x-md.card title="Состояние активности"
                       subtitle="Активность продлевается при изменении свойства с ролью и истекает через activityTimeout простоя.">
                <div class="md-ui-form-grid">
                    <div>
                        <div class="md-field__label">Комната</div>
                        <x-md.badge :tone="$roomState['active'] ? 'success' : 'neutral'">
                            {{ $roomState['active'] ? 'активна' : 'неактивна' }}
                        </x-md.badge>
                    </div>
                    <div>
                        <div class="md-field__label">Свет</div>
                        <x-md.badge :tone="$roomState['light'] ? 'warning' : 'neutral'">{{ $roomState['light'] ? 'включён' : 'нет' }}</x-md.badge>
                    </div>
                    <div>
                        <div class="md-field__label">Обогрев</div>
                        <x-md.badge :tone="$roomState['heating'] ? 'danger' : 'neutral'">{{ $roomState['heating'] ? 'включён' : 'нет' }}</x-md.badge>
                    </div>
                    <div>
                        <div class="md-field__label">Кондиционер</div>
                        <x-md.badge :tone="$roomState['condition'] ? 'info' : 'neutral'">{{ $roomState['condition'] ? 'включён' : 'нет' }}</x-md.badge>
                    </div>
                    <div>
                        <div class="md-field__label">Простой</div>
                        <span>{{ $roomState['timeout'] }} сек</span>
                    </div>
                    <div>
                        <div class="md-field__label">Последняя активность</div>
                        <span>{{ $roomState['last_activity_at'] !== '' ? $roomState['last_activity_at'] : '—' }}</span>
                    </div>
                </div>
            </x-md.card>
        @endif

        <x-md.card title="Свойства с ролью"
                   subtitle="Система собирает эти значения со всех объектов комнаты. Роль задаётся в карточке свойства.">
            @if ($sources === [])
                <x-md.empty-state icon="adjustments" title="Роль-свойства не найдены"
                                  text="Откройте свойство объекта в этой комнате и выберите роль: свет, обогрев, кондиционер или датчик." />
            @else
                <x-md.table label="Свойства активности комнаты">
                    <x-slot:head>
                        <th scope="col">Объект</th>
                        <th scope="col">Свойство</th>
                        <th scope="col">Роль</th>
                        <th scope="col">Значение</th>
                        <th scope="col">Состояние</th>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach ($sources as $source)
                            <tr>
                                <td><a href="/objects/{{ $source['object_id'] }}/edit">{{ $source['object_title'] }}</a></td>
                                <td><code class="md-table__code">{{ $source['property_title'] }}</code></td>
                                <td><x-md.badge tone="info">{{ $source['role_label'] }}</x-md.badge></td>
                                <td>{{ $source['value'] !== '' ? $source['value'] : '—' }}</td>
                                <td>
                                    <x-md.badge :tone="$source['active'] ? 'success' : 'neutral'">
                                        {{ $source['active'] ? 'активно' : 'нет' }}
                                    </x-md.badge>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-md.table>
            @endif

            <x-slot:actions>
                <x-md.button href="/objects/create?location_id={{ $location['id'] }}" icon="plus" size="sm">Добавить объект</x-md.button>
            </x-slot:actions>
        </x-md.card>

        <x-md.card title="Объекты в этой локации"
                   subtitle="Расположение хранится у объекта, поэтому один класс может быть в разных помещениях. Объект комнаты здесь не показывается.">
            @if ($roomObjects === [])
                <x-md.empty-state icon="box" title="Объектов нет"
                                  text="Добавьте свет, датчик или другое оборудование и выберите эту комнату в его карточке." />
            @else
                <x-md.table label="Объекты локации">
                    <x-slot:head>
                        <th scope="col">Объект</th>
                        <th scope="col">Описание</th>
                        <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach ($roomObjects as $locationObject)
                            <tr>
                                <td>
                                    <div class="md-table__name">
                                        <span class="md-table__icon"><i class="ti ti-box" aria-hidden="true"></i></span>
                                        <div><a href="/objects/{{ $locationObject['id'] }}/edit"><strong>{{ $locationObject['title'] }}</strong></a></div>
                                    </div>
                                </td>
                                <td>{{ $locationObject['description'] !== '' ? $locationObject['description'] : '—' }}</td>
                                <td>
                                    <div class="md-table__actions">
                                        <x-md.icon-button href="/objects/{{ $locationObject['id'] }}/edit" icon="pencil"
                                                          label="Открыть объект" size="sm" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-md.table>
            @endif
        </x-md.card>
    @elseif (!$isNew)
        <x-md.card title="Что дальше">
            <p class="md-ui-muted">
                {{ $kind === 'house'
                    ? 'Добавьте этажи и комнаты: при создании комнаты система создаст объект активности.'
                    : 'Добавьте комнаты на этот этаж: каждая получит объект в подклассе «' . $location['title'] . '».' }}
            </p>
            <div class="md-ui-form-actions">
                <x-md.button href="/locations/create" icon="plus">Добавить вложенную локацию</x-md.button>
            </div>
        </x-md.card>
    @endif
</div>
