@props([
    'name' => null,
    'id' => null,
    'options' => [],
    'selected' => '',
    'placeholder' => null,
    'multiple' => false,
    'searchable' => false,
    'label' => null,
    'description' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'optional' => false,
    'disabled' => false,
    'size' => 'md',
])

@php
    $size = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
    $hasError = $error !== null;
    $id = $id ?? 'md-select-' . substr(md5(($name ?? '') . '|' . ($label ?? '')), 0, 8);
    $selectedValues = is_array($selected) ? array_map('strval', $selected) : [(string)$selected];
    $class = implode(' ', array_filter([
        'md-select',
        $size !== 'md' ? 'md-select--' . $size : null,
        $hasError ? 'is-invalid' : null,
    ]));
    $attrs = 'class="' . e($class) . '" id="' . e($id) . '"';
    if ($name !== null) {
        $attrs .= ' name="' . e($name) . (($multiple ?? false) ? '[]' : '') . '"';
    }
    if ($required) {
        $attrs .= ' required';
    }
    if ($disabled) {
        $attrs .= ' disabled';
    }
    if ($multiple) {
        $attrs .= ' multiple';
    }
    if ($searchable) {
        $attrs .= ' data-md-searchable';
    }
    if ($hasError) {
        $attrs .= ' aria-invalid="true" aria-describedby="' . e($id) . '-help"';
    }
@endphp

<x-md.field :label="$label" :for="$id" :description="$description" :hint="$hint" :error="$error" :required="$required" :optional="$optional">
    <select {!! $attrs !!}>
        @if (!$multiple && $placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        {{-- Формы модулей передают готовые <option> слотом; UI Kit — массивом. --}}
        {{ $slot }}
        @foreach ($options as $value => $option)
            @php
                $optionValue = is_array($option) ? (string)($option['value'] ?? $value) : (string)$value;
                $optionLabel = is_array($option) ? (string)($option['label'] ?? $optionValue) : (string)$option;
                $optionDisabled = is_array($option) && !empty($option['disabled']);
            @endphp
            <option value="{{ $optionValue }}"
                    @selected(in_array($optionValue, $selectedValues, true))
                    @disabled($optionDisabled)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>
</x-md.field>
