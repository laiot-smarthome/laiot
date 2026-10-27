@php
    $isNew = $property === null;
    $field = static fn (string $key): string => (string)($draft[$key] ?? $property[$key] ?? '');
    $keepHistory = (bool)($keepHistory ?? false);
    $types = ['string' => 'Строка', 'int' => 'Целое число', 'float' => 'Дробное число', 'bool' => 'Да/Нет'];
    $currentType = $field('data_type') !== '' ? $field('data_type') : 'string';
    $isObjectProperty = $objectId > 0;
    $contextTitle = $isObjectProperty
        ? ((string)($object['title'] ?? 'объект'))
        : ((string)($class['title'] ?? ''));
    $currentOnChange = $field('on_change');
    $methodTitles = array_map(static fn (array $row): string => (string)$row['title'], $methodOptions);
    $bindingsByModule = [];
    foreach ($bindings ?? [] as $binding) {
        $bindingsByModule[$binding['module']][] = $binding['object_title'];
    }
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        :eyebrow="$isNew ? ($isObjectProperty ? 'Новое свойство объекта' : 'Новое свойство класса') : 'Карточка свойства'"
        icon="adjustments"
        :title="$isNew ? 'Новое свойство' : $property['title']"
        :lede="$isObjectProperty
            ? 'Объектное свойство действует только у объекта «' . $contextTitle . '». Имя латиницей и не должно совпадать с действующими свойствами.'
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
                : 'Свойство наследуют объекты класса и его подклассов, но имя остаётся уникальным во всей цепочке.'" />
    @endif

    <form method="post" action="{{ $isNew ? '/properties/create' : '/properties/' . $property['id'] . '/edit' }}" class="md-ui-form">
        <input type="hidden" name="_token" value="{{ $csrf }}">
        @if ($classId > 0)
            <input type="hidden" name="class_id" value="{{ $classId }}">
        @endif
        @if ($objectId > 0)
            <input type="hidden" name="object_id" value="{{ $objectId }}">
        @endif

        <x-md.card title="Параметры свойства"
                   subtitle="Имя, тип данных, история и правило вызова после записи.">
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
                    <x-md.name-field name="title" label="Системное имя" :value="$field('title')"
                                     :taken="$takenNames" required
                                     hint="Латиница, цифры и _; например status, temperature, battery." />

                    <x-md.select name="data_type" label="Тип данных" :selected="$currentType">
                        @foreach ($types as $type => $label)
                            <option value="{{ $type }}" @selected($currentType === $type)>{{ $label }}</option>
                        @endforeach
                    </x-md.select>
                </div>

                <x-md.textarea name="description" label="Описание" :value="$field('description')" rows="2"
                               hint="Что означает значение и в каких единицах оно измеряется." />

                <div class="md-ui-form-grid">
                    <x-md.select name="on_change" label="OnChange-обработчик" :selected="$currentOnChange"
                                 hint="Метод, который вызывается после изменения значения.">
                        <option value="">— нет —</option>
                        @foreach ($methodTitles as $methodTitle)
                            <option value="{{ $methodTitle }}" @selected($currentOnChange === $methodTitle)>{{ $methodTitle }}</option>
                        @endforeach
                        @if ($currentOnChange !== '' && !in_array($currentOnChange, $methodTitles, true))
                            <option value="{{ $currentOnChange }}" selected>{{ $currentOnChange }} (нет в списке)</option>
                        @endif
                    </x-md.select>

                    <x-md.checkbox name="keep_history" value="1" label="Хранить историю"
                                   description="Каждая запись значения попадёт в историю и станет доступна на графике."
                                   :checked="$keepHistory" />
                </div>

                <x-md.checkbox name="only_new_values" value="1" label="Принимать только новые значения"
                               description="Повторная запись того же значения принимается, но не запускает OnChange, историю и модули. Выключено — повтор тоже запускает цепочку."
                               :checked="$onlyNewValues" />
            </div>

            <div class="md-ui-form-actions">
                <x-md.button type="submit" icon="check">{{ $isNew ? 'Создать свойство' : 'Сохранить' }}</x-md.button>
                <x-md.button :href="$returnUrl" variant="ghost">Отмена</x-md.button>
            </div>
        </x-md.card>
    </form>

    @if (!$isNew)
        <x-md.card title="Использование"
                   subtitle="Модули и системные обработчики, которые зависят от свойства.">
            <div class="md-ui-form-stack">
                <div>
                    <div class="md-field__label">Подписки модулей</div>
                    @if ($bindingsByModule === [])
                        <p class="md-ui-muted">Модули на это свойство не подписаны.</p>
                    @else
                        <div class="md-tree-objects">
                            @foreach ($bindingsByModule as $module => $objectTitles)
                                <span class="md-tree-object">
                                    <span class="md-tree-object__icon"><i class="ti ti-plug" aria-hidden="true"></i></span>
                                    <span class="md-tree-object__body">
                                        <strong>{{ $module }}</strong>
                                        <small>{{ implode(', ', array_unique($objectTitles)) }}</small>
                                    </span>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="md-ui-form-grid">
                    <div>
                        <div class="md-field__label">OnChange</div>
                        @if ($property['on_change'] !== '')
                            <code class="md-table__code">{{ $property['on_change'] }}</code>
                        @else
                            <span class="md-ui-muted">Не задан</span>
                        @endif
                    </div>
                    <div>
                        <div class="md-field__label">История</div>
                        @if (!empty($property['keep_history']))
                            <x-md.badge tone="success">ведётся</x-md.badge>
                        @else
                            <x-md.badge tone="neutral">не ведётся</x-md.badge>
                        @endif
                    </div>
                    <div>
                        <div class="md-field__label">Повторная запись</div>
                        @if (!empty($property['only_new_values']))
                            <x-md.badge tone="info">только новые значения</x-md.badge>
                        @else
                            <x-md.badge tone="warning">запускает цепочку</x-md.badge>
                        @endif
                    </div>
                </div>
            </div>
        </x-md.card>
    @endif
</div>
