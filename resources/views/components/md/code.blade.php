@props([
    'name' => null,
    'value' => '',
    'label' => null,
    'rows' => 12,
    'hint' => null,
    'language' => 'php',
    'id' => null,
    'required' => false,
])

@php
    $id = $id ?? 'md-code-' . substr(md5(($name ?? '') . '|' . ($label ?? '')), 0, 8);
    $lineCount = max(1, substr_count((string)$value, "\n") + 1);
@endphp

<div class="md-field" x-data="{code: @js((string)$value)}">
    @if ($label !== null)
        <label class="md-field__label" for="{{ $id }}">
            {{ $label }}
            @if ($required)<span class="md-field__req" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <div class="md-code-editor">
        <pre class="md-code-editor__gutter" aria-hidden="true"
             x-text="Array.from({length: Math.max(1, code.split('\n').length)}, (_, i) => i + 1).join('\n')">{{ implode("\n", range(1, $lineCount)) }}</pre>
        <textarea class="md-code-editor__input"
                  id="{{ $id }}"
                  @if ($name !== null) name="{{ $name }}" @endif
                  rows="{{ $rows }}"
                  spellcheck="false"
                  autocapitalize="off"
                  autocomplete="off"
                  data-code-editor
                  data-language="{{ $language }}"
                  x-model="code"
                  @if ($required) required @endif>{{ $value }}</textarea>
    </div>

    @if ($hint !== null)
        <div class="md-field__hint" id="{{ $id }}-help"><span>{{ $hint }}</span></div>
    @endif
</div>
