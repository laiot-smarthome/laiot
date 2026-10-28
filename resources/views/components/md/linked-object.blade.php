@props([
    'name' => 'linked_object', 'value' => '', 'label' => 'Объект',
    'placeholder' => 'Начните вводить имя', 'propertyName' => null, 'property' => '',
    'methodName' => null, 'method' => '', 'hint' => null, 'required' => false,
])
@php
    $id = 'md-linked-' . bin2hex(random_bytes(5));
    $fields = [
        'object' => ['name' => $name, 'label' => $label, 'icon' => 'link', 'placeholder' => $placeholder, 'value' => $value],
        'property' => ['name' => $propertyName, 'label' => 'Свойство', 'icon' => 'list-details', 'placeholder' => 'Выберите свойство', 'value' => $property],
        'method' => ['name' => $methodName, 'label' => 'Метод', 'icon' => 'bolt', 'placeholder' => 'Выберите метод', 'value' => $method],
    ];
@endphp
<div {{ $attributes->class(['md-linked-object', 'md-linkedobject-editor']) }}
     x-data="mdLinkedObject({value: @js((string)$value), property: @js((string)$property), method: @js((string)$method)})"
     @md-linked-data.stop="receive($event.detail)"
     @htmx:response-error="failed()" @htmx:send-error="failed()"
     @keydown.escape.stop="close()" @click.outside="close()"
     @focusout="if (!$el.contains($event.relatedTarget)) close()">
    @foreach ($fields as $kind => $field)
        @if ($field['name'] !== null)
            <div class="md-linkedobject">
                <div class="md-linkedobject__head">
                    <label class="md-field__label md-linkedobject__label" for="{{ $id }}-{{ $kind }}">{{ $field['label'] }}@if ($kind === 'object' && $required)<span class="md-field__req" aria-hidden="true">*</span>@endif</label>
                    <span class="md-badge md-badge--outline md-linkedobject__badge" x-show="context" x-text="context?.class || 'Без класса'" x-cloak></span>
                </div>
                <div class="md-linkedobject__control">
                    <div class="md-input-shell md-linkedobject__shell">
                        <span class="md-input-icon md-linkedobject__icon"><x-md.icon :name="$field['icon']" /></span>
                        <span class="md-linkedobject__slot">
                            <input type="text" class="md-linkedobject__input" id="{{ $id }}-{{ $kind }}" name="{{ $field['name'] }}"
                                   value="{{ $field['value'] }}" placeholder="{{ $field['placeholder'] }}" autocomplete="off" spellcheck="false"
                                   x-ref="{{ $kind }}" x-model="values.{{ $kind }}"
                                   role="combobox" aria-autocomplete="list" aria-haspopup="listbox" aria-controls="{{ $id }}-list"
                                   :aria-expanded="kind === '{{ $kind }}'" :aria-activedescendant="kind === '{{ $kind }}' && active >= 0 ? '{{ $id }}-option-' + active : null"
                                   aria-describedby="{{ $id }}-{{ $kind }}-hint"
                                   @click="if (kind !== '{{ $kind }}') show('{{ $kind }}')" @input="onInput('{{ $kind }}')"
                                   @keydown.arrow-down.prevent="if (!kind) show('{{ $kind }}'); else move(1)"
                                   @keydown.arrow-up.prevent="if (!kind) show('{{ $kind }}'); else move(-1)"
                                   @keydown.enter="if (kind) { $event.preventDefault(); selectActive(); }"
                                   @required($kind === 'object' && $required)>
                        </span>
                        <button type="button" class="md-input-action md-linkedobject__inline-action" x-show="values.{{ $kind }} !== ''" @click="clear('{{ $kind }}')" aria-label="Очистить: {{ $field['label'] }}" x-cloak><x-md.icon name="x" /></button>
                        <button type="button" class="md-input-action md-linkedobject__inline-action" @click="toggle('{{ $kind }}')" :aria-expanded="kind === '{{ $kind }}'" aria-label="Показать список: {{ $field['label'] }}"><x-md.icon name="chevron-down" /></button>
                    </div>
                </div>
                <div class="md-linkedobject__foot">
                    <div class="md-field__hint md-linkedobject__hint" id="{{ $id }}-{{ $kind }}-hint" x-text="context ? 'Поиск по имени; можно ввести своё значение' : 'Выберите объект или введите имя'"></div>
                    <div class="md-linkedobject__actions">
                        <a class="md-icon-btn md-icon-btn--sm md-linkedobject__action" x-show="context" :href="context ? '/objects/' + context.id + '/edit?tab={{ $kind === 'method' ? 'methods' : 'properties' }}' : null" target="_blank" rel="noopener noreferrer" aria-label="Открыть: {{ $field['label'] }}" title="Открыть: {{ $field['label'] }}" x-cloak><x-md.icon name="external-link" /></a>
                        <button type="button" class="md-icon-btn md-icon-btn--sm md-linkedobject__action" @click="request()" :disabled="loading" aria-label="Обновить список: {{ $field['label'] }}" title="Обновить список"><x-md.icon name="refresh" /></button>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
    <div class="md-linkedobject-popover md-linkedobject-editor__menu" popover="manual" x-ref="menu" x-show="kind !== ''" x-cloak @mousedown.prevent>
        <div class="md-linkedobject-popover__list" role="listbox" id="{{ $id }}-list" :aria-label="kind === 'object' ? 'Объекты' : (kind === 'property' ? 'Свойства' : 'Методы')">
            <div class="md-linkedobject-popover__empty" x-show="loading && kind === 'object'" role="status">Загрузка…</div>
            <template x-for="(item, index) in matches" :key="item.title">
                <div role="presentation">
                    <div class="md-linkedobject-popover__group" x-show="kind === 'object' && (index === 0 || matches[index - 1].class !== item.class)" x-text="item.class || 'Без класса'"></div>
                <button type="button" class="md-linkedobject-popover__option" :class="{'is-active': active === index, 'is-selected': values[kind] === item.title}" :id="'{{ $id }}-option-' + index" role="option" :aria-selected="active === index" tabindex="-1" @click="choose(item)">
                    <span class="md-linkedobject-popover__option-main"><span class="md-linkedobject-popover__option-title" x-text="item.title"></span><span class="md-linkedobject-popover__option-meta" x-text="item.description || item.class || item.data_type || ''"></span></span>
                    <x-md.icon name="check" class="md-linkedobject-popover__check" />
                </button>
                </div>
            </template>
            <div class="md-linkedobject-popover__empty" x-show="!loading && !matches.length" role="status">Ничего не найдено. Можно ввести своё значение.</div>
        </div>
        <div class="md-linkedobject-popover__footer"><span x-text="matches.length + ' вариантов'"></span><span class="md-linkedobject-popover__keys">↑ ↓ выбор · Enter · Esc</span></div>
    </div>
    <div class="md-field__hint is-invalid" role="alert" x-show="error" x-text="error" x-cloak></div>
    @if ($hint !== null)<div class="md-field__hint">{{ $hint }}</div>@endif
    @yoyo('linked-object-data', ['object' => (string)$value])
</div>
