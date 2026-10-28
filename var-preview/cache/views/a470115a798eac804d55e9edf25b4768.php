<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
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
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
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
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
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
?>

<?php if($wrap): ?>
    <?php if (isset($component)) { $__componentOriginalb27e2288655dcbaad1cbc5cdb49a4be7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb27e2288655dcbaad1cbc5cdb49a4be7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.field','data' => ['label' => $label,'for' => $id,'description' => $description,'hint' => $hint,'error' => $error,'required' => $required,'optional' => $optional]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.field'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($label),'for' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($id),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($description),'hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($hint),'error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($error),'required' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($required),'optional' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($optional)]); ?>
        <?php echo $control; ?>

     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb27e2288655dcbaad1cbc5cdb49a4be7)): ?>
<?php $attributes = $__attributesOriginalb27e2288655dcbaad1cbc5cdb49a4be7; ?>
<?php unset($__attributesOriginalb27e2288655dcbaad1cbc5cdb49a4be7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb27e2288655dcbaad1cbc5cdb49a4be7)): ?>
<?php $component = $__componentOriginalb27e2288655dcbaad1cbc5cdb49a4be7; ?>
<?php unset($__componentOriginalb27e2288655dcbaad1cbc5cdb49a4be7); ?>
<?php endif; ?>
<?php else: ?>
    <?php echo $control; ?>

<?php endif; ?>
<?php /**PATH /var/www/html/resources/views/components/md/input.blade.php ENDPATH**/ ?>