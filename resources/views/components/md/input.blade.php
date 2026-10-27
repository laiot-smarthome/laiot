@props([
    'type' => 'text',
    'name' => null,
    'id' => null,
    'value' => '',
    'placeholder' => null,
    'label' => null,
    'description' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'optional' => false,
    'disabled' => false,
    'readonly' => false,
    'passwordToggle' => false,
    'icon' => null,
    'prefix' => null,
    'suffix' => null,
    'size' => 'md',
    'wrap' => null,
])

@php
    $size = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
    $hasError = $error !== null;
    $id = $id ?? 'md-input-' . substr(md5(($name ?? '') . '|' . ($label ?? '') . '|' . (string)$value), 0, 8);
    $wrap = $wrap ?? ($label !== null || $hint !== null || $error !== null || $description !== null);

    $attributesString = 'type="' . e($type) . '" id="' . e($id) . '"';
    if ($name !== null) {
        $attributesString .= ' name="' . e($name) . '"';
    }
    $attributesString .= ' value="' . e($value) . '"';
    if ($placeholder !== null) {
        $attributesString .= ' placeholder="' . e($placeholder) . '"';
    }
    if ($required) {
        $attributesString .= ' required';
    }
    if ($disabled) {
        $attributesString .= ' disabled';
    }
    if ($readonly) {
        $attributesString .= ' readonly';
    }
    if ($hasError) {
        $attributesString .= ' aria-invalid="true"';
    }
    if ($error !== null || $hint !== null || $description !== null) {
        $attributesString .= ' aria-describedby="' . e($id) . '-help"';
    }
    $class = implode(' ', array_filter([
        'md-input',
        $size !== 'md' ? 'md-input--' . $size : null,
        $hasError ? 'is-invalid' : null,
    ]));
    $control = '<input ' . $attributes->class([$class]) . ' ' . $attributesString
        . ($passwordToggle && $type === 'password' ? ' :type="revealed ? \'text\' : \'password\'"' : '') . '>';
    if ($passwordToggle && $type === 'password') {
        $control = '<span class="md-kit-password" x-data="{revealed: false}">' . $control
            . '<button type="button" class="md-icon-btn" @click="revealed = !revealed" :aria-label="revealed ? \'Скрыть пароль\' : \'Показать пароль\'" :aria-pressed="revealed.toString()"><i class="ti ti-eye" aria-hidden="true"></i></button></span>';
    }
    if ($icon !== null || $prefix !== null || $suffix !== null) {
        $leading = $icon !== null ? '<i class="ti ti-' . e($icon) . '" aria-hidden="true"></i>' : '';
        $leading .= $prefix !== null ? '<span>' . e($prefix) . '</span>' : '';
        $trailing = $suffix !== null ? '<span>' . e($suffix) . '</span>' : '';
        $control = '<span class="md-kit-input-shell">' . $leading . $control . $trailing . '</span>';
    }
@endphp

@if ($wrap)
    <x-md.field
        :label="$label"
        :for="$id"
        :description="$description"
        :hint="$hint"
        :error="$error"
        :required="$required"
        :optional="$optional"
    >
        {!! $control !!}
    </x-md.field>
@else
    {!! $control !!}
@endif
