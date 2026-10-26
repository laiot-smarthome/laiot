@php
    $isNew = $property === null;
    $field = static fn (string $key): string => (string)($draft[$key] ?? $property[$key] ?? '');
    $keepHistory = (bool)($draft['keep_history'] ?? $property['keep_history'] ?? false);
    $types = ['string' => 'Строка', 'int' => 'Целое число', 'float' => 'Дробное число', 'bool' => 'Да/Нет'];
    $currentType = $field('data_type') !== '' ? $field('data_type') : 'string';
    $isObjectProperty = $objectId > 0;
    $contextTitle = $isObjectProperty
        ? ((string)($object['title'] ?? 'объект'))
        : ((string)($class['title'] ?? ''));
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        :eyebrow="$isNew ? ($isObjectProperty ? 'Новое свойство объекта' : 'Новое свойство класса') : 'Карточка свойства'"
        icon="adjustments"
        :title="$isNew ? 'Новое свойство' : $property['title']"
        :lede="$isObjectProperty
            ? 'Объектное свойство действует только у объекта «' . $contextTitle . '» и переопределяет одноимённое свойство класса.'
            : 'Свойство класса задаёт тип, историю и OnChange. Значения хранятся у каждого объекта, а не у класса.'"
        :meta="[
            ['icon' => $isObjectProperty ? 'box' : 'hierarchy', 'label' => $isObjectProperty ? 'Объект' : 'Класс', 'value' => $contextTitle !== '' ? $contextTitle : 'не задан'],
            ['icon' => 'list', 'label' => 'Тип', 'value' => $types[$currentType] ?? $currentType],
        ]">
        <x-slot:actions>
            <x-md.button :href="$returnUrl" variant="secondary" icon="arrow-left">Назад</x-md.button>
            @if (!$isNew)
                <form method="post" action="/properties/{{ $property['id'] }}/delete" class="md-inline-form"
                      data-md-confirm="Удалить свойство «{{ $property['title'] }}»? Значения и история останутся в БД.">
                    <input type="hidden" name="_token" value="{{ $csrf }}">
                    <x-md.button type="submit" variant="danger" icon="trash">Удалить</x-md.button>
                </form>
            @endif
        </x-slot:actions>
    </x-md.page-hero>

    @if ($error !== null)
        <x-md.alert type="danger" title="Не удалось сохранить свойство" :text="$error" />
    @endif

    @if ($class !== null || $object !== null)
        <x-md.hint
            tone="info"
            :title="$isObjectProperty ? 'Объектное свойство' : 'Свойство класса'"
            :text="$isObjectProperty
                ? 'Значение и история этого свойства принадлежат только объекту «' . $contextTitle . '».'
                : 'Свойство наследуют объекты класса и его подклассов. У класса без объектов значений нет.'" />
    @endif

    <form method="post" action="{{ $isNew ? '/properties/create' : '/properties/' . $property['id'] . '/edit' }}" class="md-ui-form">
        <input type="hidden" name="_token" value="{{ $csrf }}">
        @if ($classId > 0)
            <input type="hidden" name="class_id" value="{{ $classId }}">
        @endif
        @if ($objectId > 0)
            <input type="hidden" name="object_id" value="{{ $objectId }}">
        @endif

        <x-md.card title="Параметры свойства">
            <div class="md-ui-form-stack">
                @if ($classId <= 0 && $objectId <= 0)
                    <div class="md-ui-form-grid">
                        <x-md.select name="class_id" label="Класс" :selected="$classId" required
                                     hint="Свойство принадлежит классу и наследуется его объектами.">
                            @foreach ($classes as $row)
                                <option value="{{ $row['id'] }}" @selected($classId === (int)$row['id'])>{{ $row['title'] }}</option>
                            @endforeach
                        </x-md.select>
                    </div>
                @endif

                <div class="md-ui-form-grid">
                    <x-md.input name="title" label="Название" :value="$field('title')" required
                                hint="Латиницей без пробелов: temperature, status, battery." />
                    <x-md.select name="data_type" label="Тип данных" :selected="$currentType">
                        @foreach ($types as $type => $label)
                            <option value="{{ $type }}" @selected($currentType === $type)>{{ $label }}</option>
                        @endforeach
                    </x-md.select>
                    <x-md.input name="on_change" label="OnChange-обработчик" :value="$field('on_change')"
                                hint="Метод объекта вида MethodName, вызывается после записи значения. Ищется с учётом наследования." />
                </div>

                <x-md.textarea name="description" label="Описание" :value="$field('description')" rows="2"
                               hint="Что означает значение и в каких единицах оно измеряется." />

                <x-md.checkbox name="keep_history" value="1" label="Хранить историю"
                               description="Каждая запись значения попадёт в историю и станет доступна на графике."
                               :checked="$keepHistory" />
            </div>
        </x-md.card>

        <div class="md-ui-form-actions">
            <x-md.button type="submit" icon="check">{{ $isNew ? 'Создать свойство' : 'Сохранить' }}</x-md.button>
            <x-md.button :href="$returnUrl" variant="ghost">Отмена</x-md.button>
        </div>
    </form>
</div>
