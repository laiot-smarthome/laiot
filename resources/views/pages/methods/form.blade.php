@php
    $isNew = $method === null;
    $field = static fn (string $key): string => (string)($draft[$key] ?? $method[$key] ?? '');
    $classId = (int)($draft['class_id'] ?? $method['class_id'] ?? 0);
    $classNames = [];
    foreach ($classes as $class) {
        $classNames[$class['id']] = $class['title'];
    }
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        :eyebrow="$isNew ? 'Новый метод' : 'Карточка метода'"
        icon="code"
        :title="$isNew ? 'Новый метод' : $method['title']"
        :lede="$isNew ? 'Метод — PHP-код класса. Он выполняется при вызове из сценария, API или OnChange.' : 'Редактор не запускает код: вызов выполняется сценариями и API.'"
        :meta="$isNew ? [] : [
            ['icon' => 'hierarchy', 'label' => 'Класс', 'value' => $classNames[$classId] ?? 'Без класса'],
        ]">
        <x-slot:actions>
            <x-md.button href="/methods" variant="secondary" icon="arrow-left">К методам</x-md.button>
            @if (!$isNew)
                <form method="post" action="/methods/{{ $method['id'] }}/delete" class="md-inline-form"
                      data-md-confirm="Удалить метод «{{ $method['title'] }}»?">
                    <input type="hidden" name="_token" value="{{ $csrf }}">
                    <x-md.button type="submit" variant="danger" icon="trash">Удалить</x-md.button>
                </form>
            @endif
        </x-slot:actions>
    </x-md.page-hero>

    @if ($error !== null)
        <x-md.alert type="danger" title="Не удалось сохранить метод" :text="$error" />
    @endif

    <form method="post" action="{{ $isNew ? '/methods/create' : '/methods/' . $method['id'] . '/edit' }}" class="md-ui-form">
        <input type="hidden" name="_token" value="{{ $csrf }}">

        <x-md.card title="Описание метода">
            <div class="md-ui-form-stack">
                <div class="md-ui-form-grid">
                    <x-md.select name="class_id" label="Класс" :selected="$classId">
                        <option value="0">Без класса</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class['id'] }}" @selected($classId === $class['id'])>{{ $class['title'] }}</option>
                        @endforeach
                    </x-md.select>
                    <x-md.input name="title" label="Название" :value="$field('title')" required
                                hint="Латиницей: onTemperatureChange, turnOn, refreshStatus." />
                </div>
                <x-md.textarea name="description" label="Описание" :value="$field('description')" rows="2"
                               hint="Когда вызывается и что меняет." />
            </div>
        </x-md.card>

        <x-md.card title="Код">
            <x-md.code name="code" :value="$field('code')" rows="18"
                       hint="PHP без открывающего тега. Доступны $this, gg(), sg(), callMethod()." />
            <x-md.hint tone="warning" title="Код выполняется при вызове"
                       text="Сохранение безопасно, но метод запустится, когда его вызовет сценарий, OnChange или API." />
        </x-md.card>

        <div class="md-ui-form-actions">
            <x-md.button type="submit" icon="check">{{ $isNew ? 'Создать метод' : 'Сохранить' }}</x-md.button>
            <x-md.button href="/methods" variant="ghost">Отмена</x-md.button>
        </div>
    </form>
</div>
