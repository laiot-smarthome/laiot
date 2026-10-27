@props([
    'label' => null,
    'description' => null,
    'name' => null,
    'id' => null,
    'value' => '1',
    'checked' => false,
    'disabled' => false,
    'required' => false,
])

@php
    $id = $id ?? 'md-check-' . substr(md5(($name ?? '') . '|' . ($label ?? '') . '|' . $value), 0, 8);
@endphp

<label class="md-check" for="{{ $id }}">
    <input type="checkbox"
           data-md-raw-checkbox
           id="{{ $id }}"
           {{ $attributes->class(['md-check__input']) }}
           @if ($name !== null) name="{{ $name }}" @endif
           value="{{ $value }}"
           @checked($checked)
           @disabled($disabled)
           @required($required)>
    <span class="md-check__box" aria-hidden="true"><x-md.icon name="check2" /></span>
    @if ($label !== null || $description !== null)
        <span class="md-check__text">
            @if ($label !== null)<span class="md-check__label">{{ $label }}</span>@endif
            @if ($description !== null)<span class="md-check__desc">{{ $description }}</span>@endif
        </span>
    @endif
</label>
