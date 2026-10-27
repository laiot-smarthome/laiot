<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        :eyebrow="$location === null ? 'Новая локация' : 'Карточка локации'"
        icon="map-pin"
        :title="$location === null ? 'Новая локация' : $location['title']"
        :lede="$location === null
            ? 'Локация — помещение или зона. Она задаётся у объекта, а не у класса.'
            : 'Приоритет задаёт порядок помещения в списках. Объекты этого помещения перечислены ниже.'">
        <x-slot:actions>
            <x-md.button href="/locations" variant="secondary" icon="arrow-left">К локациям</x-md.button>
            @if ($location !== null)
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

    <form method="post" action="{{ $location === null ? '/locations/create' : '/locations/' . $location['id'] . '/edit' }}" class="md-ui-form">
        <input type="hidden" name="_token" value="{{ $csrf }}">

        <x-md.card title="Параметры локации">
            <div class="md-ui-form-stack">
                <div class="md-ui-form-grid">
                    <x-md.input name="title" label="Название" :value="$title" required
                                hint="Например: «Кухня», «Гостиная», «Улица»." />
                    <x-md.input name="priority" type="number" label="Приоритет" :value="(string)$priority"
                                hint="Чем больше число, тем выше локация в списках." />
                </div>
            </div>
        </x-md.card>

        <div class="md-ui-form-actions">
            <x-md.button type="submit" icon="check">{{ $location === null ? 'Создать локацию' : 'Сохранить' }}</x-md.button>
            <x-md.button href="/locations" variant="ghost">Отмена</x-md.button>
        </div>
    </form>

    @if ($location !== null)
        <x-md.card title="Объекты в этой локации"
                   subtitle="Расположение хранится у объекта, поэтому один класс может быть в разных помещениях.">
            <x-slot:actions>
                <x-md.button href="/objects/create?location_id={{ $location['id'] }}" icon="plus" size="sm">Добавить объект</x-md.button>
            </x-slot:actions>

            @if ($objects === [])
                <x-md.empty-state icon="box" title="Объектов нет"
                                  text="Добавьте объект и выберите это помещение в его карточке." />
            @else
                <x-md.table label="Объекты локации">
                    <x-slot:head>
                        <th scope="col">Объект</th>
                        <th scope="col">Описание</th>
                        <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach ($objects as $locationObject)
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
    @endif
</div>
