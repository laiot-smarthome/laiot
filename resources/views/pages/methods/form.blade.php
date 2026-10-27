@php
    $isNew = $method === null;
    $field = static fn (string $key): string => (string)($draft[$key] ?? $method[$key] ?? '');
    $isObjectMethod = $objectId > 0;
    $contextTitle = $isObjectMethod
        ? ((string)($object['title'] ?? 'объект'))
        : ((string)($class['title'] ?? ''));
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        :eyebrow="$isNew ? ($isObjectMethod ? 'Новый метод объекта' : 'Новый метод класса') : 'Карточка метода'"
        icon="code"
        :title="$isNew ? 'Новый метод' : $method['title']"
        :lede="$isObjectMethod
            ? 'Объектный метод действует только у объекта «' . $contextTitle . '» и переопределяет одноимённый метод класса.'
            : 'Метод класса наследуют подклассы и их объекты. Редактор не запускает код: вызов идёт из сценария, API или OnChange.'"
        :meta="[
            ['icon' => $isObjectMethod ? 'box' : 'hierarchy', 'label' => $isObjectMethod ? 'Объект' : 'Класс', 'value' => $contextTitle !== '' ? $contextTitle : 'не задан'],
        ]">
        <x-slot:actions>
            <x-md.button :href="$returnUrl" variant="secondary" icon="arrow-left">Назад</x-md.button>
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

    @if ($class !== null || $object !== null)
        <x-md.hint
            tone="info"
            :title="$isObjectMethod ? 'Объектный метод' : 'Метод класса'"
            :text="$isObjectMethod
                ? 'Метод доступен только объекту «' . $contextTitle . '»; остальные объекты класса его не видят.'
                : 'Метод наследуют подклассы и все объекты этого класса. Одноимённый метод подкласса переопределяет родительский.'" />
    @endif

    <form method="post" action="{{ $isNew ? '/methods/create' : '/methods/' . $method['id'] . '/edit' }}" class="md-ui-form">
        <input type="hidden" name="_token" value="{{ $csrf }}">
        @if ($classId > 0)
            <input type="hidden" name="class_id" value="{{ $classId }}">
        @endif
        @if ($objectId > 0)
            <input type="hidden" name="object_id" value="{{ $objectId }}">
        @endif

        <div class="md-ui-form-stack">
            <x-md.card title="Описание метода">
                <div class="md-ui-form-stack">
                    @if ($classId <= 0 && $objectId <= 0)
                        <div class="md-ui-form-grid">
                            <x-md.select name="class_id" label="Класс" :selected="$classId" required
                                         hint="Метод принадлежит классу и наследуется его объектами.">
                                @foreach ($classes as $row)
                                    <option value="{{ $row['id'] }}" @selected($classId === (int)$row['id'])>{{ $row['title'] }}</option>
                                @endforeach
                            </x-md.select>
                        </div>
                    @endif

                    <div class="md-ui-form-grid">
                        <x-md.input name="title" label="Название" :value="$field('title')" required
                                    hint="Латиницей: onTemperatureChange, turnOn, refreshStatus." />
                    </div>
                    <x-md.textarea name="description" label="Описание" :value="$field('description')" rows="2"
                                   hint="Когда вызывается и что меняет." />
                </div>
            </x-md.card>

            <x-md.card title="Код">
                <x-md.code name="code" :value="$field('code')" :error="$codeError" rows="18"
                           hint="PHP без открывающего тега. Доступны $this, gg(), sg(), callMethod()." />
            </x-md.card>

            <x-md.card title="Как выполняется" icon="player-play"
                      subtitle="Сохранение код не выполняет: метод запускается только при вызове">
                <x-md.hint tone="warning" title="Код выполняется при вызове"
                           text="Метод запустится, когда его вызовет сценарий, OnChange или API. Синтаксис проверяется перед сохранением: при ошибке остаётся предыдущая версия." />
            </x-md.card>
        </div>

        <div class="md-ui-form-actions">
            <x-md.button type="submit" icon="check">{{ $isNew ? 'Создать метод' : 'Сохранить' }}</x-md.button>
            <x-md.button :href="$returnUrl" variant="ghost">Отмена</x-md.button>
        </div>
    </form>
</div>
