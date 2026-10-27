@props([
    'label' => null,
    'description' => null,
    'name' => null,
    'id' => null,
    'value' => '1',
    'checked' => false,
    'disabled' => false,
    'required' => false,
    'ariaLabel' => null,
])

@php
    $id = $id ?? 'md-switch-' . substr(md5(($name ?? '') . '|' . ($label ?? '') . '|' . $value), 0, 8);
@endphp

<label class="md-switch" for="{{ $id }}">
    <input type="checkbox"
           data-md-raw-checkbox
           id="{{ $id }}"
           {{ $attributes->class(['md-switch__input']) }}
           @if ($name !== null) name="{{ $name }}" @endif
           value="{{ $value }}"
           @checked($checked)
           @disabled($disabled)
           @required($required)
           @if ($label === null && $ariaLabel !== null) aria-label="{{ $ariaLabel }}" @endif>
    <span class="md-switch__track" aria-hidden="true"><span class="md-switch__thumb"></span></span>
    @if ($label !== null || $description !== null)
        <span class="md-switch__text">
            @if ($label !== null)<span class="md-switch__label">{{ $label }}</span>@endif
            @if ($description !== null)<span class="md-switch__desc">{{ $description }}</span>@endif
        </span>
    @endif
</label>
