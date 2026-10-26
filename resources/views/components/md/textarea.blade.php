@props([
    'name' => null,
    'id' => null,
    'value' => '',
    'placeholder' => null,
    'label' => null,
    'description' => null,
    'hint' => null,
    'error' => null,
    'rows' => 4,
    'required' => false,
    'optional' => false,
    'disabled' => false,
    'readonly' => false,
    'size' => 'md',
])

@php
    $size = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
    $hasError = $error !== null;
    $id = $id ?? 'md-textarea-' . substr(md5(($name ?? '') . '|' . ($label ?? '')), 0, 8);
    $class = implode(' ', array_filter([
        'md-textarea',
        $size !== 'md' ? 'md-textarea--' . $size : null,
        $hasError ? 'is-invalid' : null,
    ]));
    $attrs = 'class="' . e($class) . '" id="' . e($id) . '" rows="' . max(2, (int)$rows) . '"';
    if ($name !== null) {
        $attrs .= ' name="' . e($name) . '"';
    }
    if ($placeholder !== null) {
        $attrs .= ' placeholder="' . e($placeholder) . '"';
    }
    if ($required) {
        $attrs .= ' required';
    }
    if ($disabled) {
        $attrs .= ' disabled';
    }
    if ($readonly) {
        $attrs .= ' readonly';
    }
    if ($hasError) {
        $attrs .= ' aria-invalid="true" aria-describedby="' . e($id) . '-help"';
    }
@endphp

<x-md.field :label="$label" :for="$id" :description="$description" :hint="$hint" :error="$error" :required="$required" :optional="$optional">
    <textarea {!! $attrs !!}>{{ $value }}</textarea>
</x-md.field>
