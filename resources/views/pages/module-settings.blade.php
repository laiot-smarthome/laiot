<div class="md-ui-page md-ui-page--stack" x-data @module-subscriptions-open.window="$store.mdModal.show('module-subscriptions')"
     @if ($objectId > 0) x-init="$nextTick(() => $store.mdModal.show('module-subscriptions'))" @endif>
    <x-md.page-hero
        eyebrow="Модули"
        icon="settings"
        :title="$module['name'] . ' — настройки'"
        lede="Параметры подключения и свойства, с которыми работает модуль."
        :meta="[
            ['icon' => 'puzzle', 'label' => 'ID', 'value' => $module['id']],
            ['icon' => 'tag', 'label' => 'Версия', 'value' => 'v' . $module['version']],
        ]">
        <x-slot:actions>
            <x-md.button href="/admin/modules" variant="secondary" icon="arrow-left">К модулям</x-md.button>
            @if ($hasSubscriber)
                <x-md.button href="{{ '/module/' . $module['id'] }}" variant="ghost" icon="arrow-right">Открыть модуль</x-md.button>
            @endif
        </x-slot:actions>
    </x-md.page-hero>


    @if ($schema !== [])
        <form method="post" action="/admin/modules/{{ $module['id'] }}/settings" class="md-ui-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">

            <x-md.card title="Параметры" icon="settings">
                <div class="md-ui-form-stack">
                    @foreach ($schema as $name => $field)
                        @if ($field['type'] === 'bool')
                            <input type="hidden" name="{{ $name }}" value="0">
                            <x-md.switch
                                name="{{ $name }}"
                                :label="$field['title']"
                                :description="$field['description'] !== '' ? $field['description'] : null"
                                :checked="(bool)($values[$name] ?? false)" />
                        @elseif ($field['type'] === 'select')
                            <x-md.select
                                name="{{ $name }}"
                                :label="$field['title']"
                                :description="$field['description'] !== '' ? $field['description'] : null"
                                :options="$field['options']"
                                :selected="$values[$name] ?? ''" />
                        @elseif ($field['type'] === 'text')
                            <x-md.textarea
                                name="{{ $name }}"
                                :label="$field['title']"
                                :description="$field['description'] !== '' ? $field['description'] : null"
                                :value="$values[$name] ?? ''"
                                rows="5" />
                        @else
                            <x-md.input
                                :type="$field['type'] === 'int' ? 'number' : 'text'"
                                name="{{ $name }}"
                                :label="$field['title']"
                                :description="$field['description'] !== '' ? $field['description'] : null"
                                :value="$values[$name] ?? ''" />
                        @endif
                    @endforeach
                </div>
            </x-md.card>

            <div class="md-ui-form-actions">
                <x-md.button type="submit" icon="check">Сохранить</x-md.button>
                <x-md.button href="/admin/modules" variant="ghost">Отмена</x-md.button>
            </div>
        </form>
    @endif

    @if ($hasSubscriber)
        <x-md.card title="Подписки на свойства" icon="link" subtitle="Какие изменения передавать модулю.">
            <div class="md-ui-actions">
                <x-md.button variant="secondary" icon="adjustments" @click="$store.mdModal.show('module-subscriptions')">Настроить подписки</x-md.button>
                <x-md.badge tone="neutral">{{ count($subscriptions) }}</x-md.badge>
            </div>
            @if ($subscriptions === [])
                <x-md.empty-state icon="link" title="Подписок пока нет" text="Выберите объект и отметьте нужные свойства." />
            @else
                <x-md.table label="Подписки модуля">
                    <x-slot:head><th scope="col">Объект</th><th scope="col">Свойство</th><th scope="col"></th></x-slot:head>
                    <x-slot:body>
                        @foreach ($subscriptions as $subscription)
                            <tr>
                                <td>{{ $subscription['object_title'] }}</td>
                                <td><code class="md-table__code">{{ $subscription['property_title'] }}</code></td>
                                <td><x-md.button :href="'/admin/modules/' . $module['id'] . '/settings?object_id=' . $subscription['object_id']" variant="ghost" size="sm" icon="edit">Изменить</x-md.button></td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-md.table>
            @endif
        </x-md.card>

        <x-md.modal id="module-subscriptions" title="Настроить подписки" size="lg">
            @if ($objects === [])
                <x-md.empty-state icon="box" title="Объектов пока нет"
                                  text="Создайте объект и свойства в разделе «Классы», затем вернитесь сюда." />
            @else
                <form method="get" action="/admin/modules/{{ $module['id'] }}/settings" class="md-ui-form">
                    <div class="md-ui-form-stack">
                        <x-md.select name="object_id" label="Объект" :options="array_column($objects, 'title', 'id')"
                                     :selected="$objectId" placeholder="Выберите объект" />
                    </div>
                    <div class="md-ui-form-actions">
                        <x-md.button type="submit" variant="secondary" icon="search">Показать свойства</x-md.button>
                    </div>
                </form>

                @if ($objectId > 0)
                    @if ($properties === [])
                        <x-md.hint tone="info" title="У объекта нет свойств"
                                   text="Добавьте свойства класса или объекта — после этого подписку можно будет сохранить." />
                    @else
                        <form method="post" action="/admin/modules/{{ $module['id'] }}/subscriptions" class="md-ui-form">
                            <input type="hidden" name="_token" value="{{ $csrf }}">
                            <input type="hidden" name="object_id" value="{{ $objectId }}">
                            <div class="md-ui-form-stack">
                                @foreach ($properties as $property)
                                    <x-md.checkbox
                                        name="properties[]"
                                        :value="$property['id']"
                                        :label="$property['title']"
                                        :description="$property['origin'] === 'object' ? 'свойство объекта' : 'свойство класса'"
                                        :checked="isset($attached[$property['id']])" />
                                @endforeach
                            </div>
                            <div class="md-ui-form-actions">
                                <x-md.button type="submit" icon="check">Сохранить подписки</x-md.button>
                            </div>
                        </form>
                    @endif
                @endif
            @endif
        </x-md.modal>
    @endif
</div>
