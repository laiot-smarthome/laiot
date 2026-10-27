@props(['name' => null, 'id' => null, 'value' => '1', 'label' => null, 'description' => null, 'checked' => false, 'disabled' => false, 'required' => false])

@php($id = $id ?? 'md-radio-' . substr(md5(($name ?? '') . '|' . $value . '|' . ($label ?? '')), 0, 8))
<label class="md-check md-radio" for="{{ $id }}">
    <input type="radio" id="{{ $id }}" @if ($name !== null) name="{{ $name }}" @endif
           value="{{ $value }}" @checked($checked) @disabled($disabled) @required($required)
           {{ $attributes->class(['md-check__input', 'md-radio__input']) }}>
    <span class="md-check__box md-radio__dot" aria-hidden="true"></span>
    @if ($label !== null || $description !== null)
        <span class="md-check__text">
            @if ($label !== null)<span class="md-check__label">{{ $label }}</span>@endif
            @if ($description !== null)<span class="md-check__desc">{{ $description }}</span>@endif
        </span>
    @endif
</label>
