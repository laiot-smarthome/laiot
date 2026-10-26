@props([
    'name' => 'linked_object',
    'value' => '',
    'label' => 'Объект',
    'placeholder' => 'Начните вводить имя',
    'propertyName' => null,
    'property' => '',
    'methodName' => null,
    'method' => '',
    'hint' => null,
    'required' => false,
])

@php
    $id = 'md-linked-object-' . substr(md5((string)$name . '|' . (string)$label), 0, 8);
@endphp

<div class="md-field md-linked-object"
     x-data="mdLinkedObject({
        value: @js((string)$value),
        property: @js((string)$property),
        method: @js((string)$method),
        propertyName: @js((string)($propertyName ?? '')),
        methodName: @js((string)($methodName ?? ''))
     })"
     @click.outside="close()">
    <label class="md-field__label" for="{{ $id }}">
        {{ $label }}
        @if ($required)<span class="md-field__req" aria-hidden="true">*</span>@endif
    </label>

    <div class="md-linked-object__control">
        <input class="md-input md-linked-object__input"
               type="text"
               id="{{ $id }}"
               name="{{ $name }}"
               value="{{ $value }}"
               placeholder="{{ $placeholder }}"
               autocomplete="off"
               spellcheck="false"
               x-ref="input"
               x-model="query"
               @input="onInput()"
               @focus="open = true"
               @keydown.arrow-down.prevent="move(1)"
               @keydown.arrow-up.prevent="move(-1)"
               @keydown.enter.prevent="selectActive()"
               @keydown.escape="close()"
               @if ($required) required @endif>

        <button type="button" class="md-linked-object__clear" x-show="query !== ''" @click="clear()" aria-label="Очистить">
            <x-md.icon name="x" />
        </button>

        <div class="md-linked-object__menu" x-show="open" x-cloak role="listbox">
            <div class="md-linked-object__state" x-show="loading">Ищем…</div>
            <div class="md-linked-object__state" x-show="error !== ''" x-text="error"></div>
            <template x-for="(item, index) in results" :key="item.title">
                <button type="button"
                        class="md-linked-object__option"
                        :class="{'is-active': index === active}"
                        @click="choose(item)"
                        role="option">
                    <span class="md-linked-object__title" x-text="item.title"></span>
                    <span class="md-linked-object__meta" x-text="item.class"></span>
                </button>
            </template>
            <div class="md-linked-object__state" x-show="!loading && error === '' && results.length === 0 && query !== ''">
                Объекты не найдены
            </div>
        </div>
    </div>

    @if ($propertyName !== null)
        <input type="hidden" name="{{ $propertyName }}" x-model="property">
    @endif
    @if ($methodName !== null)
        <input type="hidden" name="{{ $methodName }}" x-model="method">
    @endif

    <div class="md-linked-object__context" x-show="context" x-cloak>
        <div class="md-linked-object__context-head">
            <x-md.badge tone="info" x-text="context?.class || 'Без класса'"></x-md.badge>
            <span class="md-muted">Свойства и методы класса</span>
        </div>
        <div class="md-linked-object__context-grid">
            @if ($propertyName !== null)
                <label class="md-field">
                    <span class="md-field__label">Свойство</span>
                    <select class="md-select" x-model="property">
                        <option value="">Не выбрано</option>
                        <template x-for="item in (context?.properties || [])" :key="item.title">
                            <option :value="item.title" x-text="item.title"></option>
                        </template>
                    </select>
                </label>
            @endif
            @if ($methodName !== null)
                <label class="md-field">
                    <span class="md-field__label">Метод</span>
                    <select class="md-select" x-model="method">
                        <option value="">Не выбрано</option>
                        <template x-for="item in (context?.methods || [])" :key="item.title">
                            <option :value="item.title" x-text="item.title"></option>
                        </template>
                    </select>
                </label>
            @endif
        </div>
    </div>

    @if ($hint !== null)
        <div class="md-field__hint"><span>{{ $hint }}</span></div>
    @endif
</div>
