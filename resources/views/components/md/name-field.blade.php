@props([
    'name' => 'title',
    'label' => 'Системное имя',
    'value' => '',
    'taken' => [],
    'hint' => null,
    'error' => null,
    'required' => true,
    'placeholder' => null,
    'description' => null,
])

@php
    $id = 'md-name-' . substr(md5($name . '|' . $label . '|' . (string)$value), 0, 10);
    $statusId = $id . '-status';
    $taken = array_values(array_map(static fn (mixed $item): string => (string)$item, (array)$taken));
    $hint ??= 'Латиница, цифры и _; первая — буква или _. Например: KitchenLight.';
@endphp

{{--
    Поле системного имени: подсказка, серверная ошибка и живой статус
    «свободно / занято / недопустимые символы» в одном компоненте.
--}}
<div {{ $attributes->class(['md-field', 'md-name-field', 'is-invalid' => $error !== null]) }}
     x-data="mdNameCheck(@js(['initial' => (string)$value, 'taken' => $taken]))">
    <label class="md-field__label" for="{{ $id }}">
        {{ $label }}
        @if ($required)<span class="md-field__req" aria-hidden="true">*</span>@endif
    </label>

    <div class="md-field__control">
        <input type="text"
               id="{{ $id }}"
               name="{{ $name }}"
               class="md-input"
               value="{{ $value }}"
               maxlength="191"
               autocomplete="off"
               spellcheck="false"
               placeholder="{{ $placeholder ?? 'KitchenLight' }}"
               @if ($required) required @endif
               x-model="value"
               @input="check()"
               :class="{'is-invalid': isInvalid, 'is-valid': isFree}"
               :aria-invalid="isInvalid ? 'true' : 'false'"
               @if ($error !== null) aria-invalid="true" @endif
               aria-describedby="{{ $statusId }}">
    </div>

    @if ($description !== null)
        <div class="md-field__hint md-name-field__description">{{ $description }}</div>
    @endif

    <div class="md-name-field__status" id="{{ $statusId }}" aria-live="polite">
        <span class="md-name-field__message md-name-field__message--idle"
              x-show="status === 'idle'">
            <x-md.icon name="info-circle" />{{ $hint }}
        </span>
        <span class="md-name-field__message"
              :class="'md-name-field__message--' + tone"
              x-show="status !== 'idle'" x-cloak>
            <i class="ti" :class="'ti-' + icon" aria-hidden="true"></i>
            <span x-text="message"></span>
        </span>
    </div>

    @if ($error !== null)
        <div class="md-field__error" role="alert" x-show="!touched">
            <x-md.icon name="alert-circle" /><span>{{ $error }}</span>
        </div>
    @endif
</div>
