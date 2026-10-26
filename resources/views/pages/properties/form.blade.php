@php
    $isNew = $property === null;
    $field = static fn (string $key): string => (string)($draft[$key] ?? $property[$key] ?? '');
    $classId = (int)($draft['class_id'] ?? $property['class_id'] ?? 0);
    $keepHistory = (bool)($draft['keep_history'] ?? $property['keep_history'] ?? false);
    $types = ['string' => 'Строка', 'int' => 'Целое число', 'float' => 'Дробное число', 'bool' => 'Да/Нет'];
    $currentType = $field('data_type') !== '' ? $field('data_type') : 'string';
    $classNames = [];
    foreach ($classes as $class) {
        $classNames[$class['id']] = $class['title'];
    }
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        :eyebrow="$isNew ? 'Новое свойство' : 'Карточка свойства'"
        icon="adjustments"
        :title="$isNew ? 'Новое свойство' : $property['title']"
        :lede="$isNew ? 'Свойство задаёт typed-значение класса: тип, историю и обработчик изменения.' : 'Значения свойства хранятся у каждого объекта класса.'"
        :meta="$isNew ? [] : [
            ['icon' => 'hierarchy', 'label' => 'Класс', 'value' => $classNames[$classId] ?? 'Глобальное'],
            ['icon' => 'list', 'label' => 'Тип', 'value' => $types[$currentType] ?? $currentType],
        ]">
        <x-slot:actions>
            <x-md.button href="/properties" variant="secondary" icon="arrow-left">К свойствам</x-md.button>
            @if (!$isNew)
                <form method="post" action="/properties/{{ $property['id'] }}/delete" class="md-inline-form"
                      data-md-confirm="Удалить свойство «{{ $property['title'] }}»?">
                    <input type="hidden" name="_token" value="{{ $csrf }}">
                    <x-md.button type="submit" variant="danger" icon="trash">Удалить</x-md.button>
                </form>
            @endif
        </x-slot:actions>
    </x-md.page-hero>

    @if ($error !== null)
        <x-md.alert type="danger" title="Не удалось сохранить свойство" :text="$error" />
    @endif

    <form method="post" action="{{ $isNew ? '/properties/create' : '/properties/' . $property['id'] . '/edit' }}" class="md-ui-form">
        <input type="hidden" name="_token" value="{{ $csrf }}">

        <x-md.card title="Параметры свойства">
            <div class="md-ui-form-stack">
                <div class="md-ui-form-grid">
                    <x-md.select name="class_id" label="Класс" :selected="$classId"
                                 hint="Глобальное свойство доступно объектам без класса.">
                        <option value="0">Глобальное (без класса)</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class['id'] }}" @selected($classId === $class['id'])>{{ $class['title'] }}</option>
                        @endforeach
                    </x-md.select>
                    <x-md.input name="title" label="Название" :value="$field('title')" required
                                hint="Латиницей без пробелов: temperature, status, battery." />
                    <x-md.select name="data_type" label="Тип данных" :selected="$currentType">
                        @foreach ($types as $type => $label)
                            <option value="{{ $type }}" @selected($currentType === $type)>{{ $label }}</option>
                        @endforeach
                    </x-md.select>
                    <x-md.input name="on_change" label="OnChange-обработчик" :value="$field('on_change')"
                                hint="Метод объекта вида MethodName, вызывается после записи значения." />
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
            <x-md.button href="/properties" variant="ghost">Отмена</x-md.button>
        </div>
    </form>
</div>
