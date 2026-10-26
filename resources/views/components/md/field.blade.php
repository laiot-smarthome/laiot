@props([
    'label' => null,
    'for' => null,
    'description' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'optional' => false,
])

@php
    $hasError = $error !== null;
    $help = $error ?? $hint ?? $description;
    $helpId = $for !== null ? $for . '-help' : null;
@endphp

<div {{ $attributes->class(['md-field', 'is-invalid' => $hasError]) }}>
    @if ($label !== null)
        <label class="md-field__label" @if ($for !== null) for="{{ $for }}" @endif>
            {{ $label }}
            @if ($required)<span class="md-field__req" aria-hidden="true">*</span>@endif
            @if ($optional)<span class="md-field__optional">необязательно</span>@endif
        </label>
    @endif

    <div class="md-field__control">{{ $slot }}</div>

    @if ($help !== null)
        <div @class(['md-field__error' => $hasError, 'md-field__hint' => !$hasError])
             @if ($hasError) role="alert" @endif
             @if ($helpId !== null) id="{{ $helpId }}" @endif>
            @if ($hasError)<x-md.icon name="exclamation-circle" />@endif
            <span>{{ $help }}</span>
        </div>
    @endif
</div>
