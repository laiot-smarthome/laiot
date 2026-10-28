<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
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
    $id = $id ?? 'md-select-' . substr(md5(($name ?? '') . '|' . ($label ?? '')), 0, 8);
    $selectedValues = is_array($selected) ? array_map('strval', $selected) : [(string)$selected];
    $class = implode(' ', array_filter([
        'md-select',
        $size !== 'md' ? 'md-select--' . $size : null,
        $hasError ? 'is-invalid' : null,
    ]));
    $attrs = (string)$attributes->class([$class]) . ' id="' . e($id) . '"';
    if ($name !== null) {
        $attrs .= ' name="' . e($name) . ($multiple && !str_ends_with($name, '[]') ? '[]' : '') . '"';
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
        $attrs .= ' x-data="mdSearchSelect"';
    }
    if ($hasError) {
        $attrs .= ' aria-invalid="true"';
    }
    if ($error !== null || $hint !== null || $description !== null) {
        $attrs .= ' aria-describedby="' . e($id) . '-help"';
    }
?>

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
    <select <?php echo $attrs; ?>>
        <?php if(!$multiple && $placeholder !== null): ?>
            <option value=""><?php echo e($placeholder); ?></option>
        <?php endif; ?>
        
        <?php echo e($slot); ?>

        <?php $__currentLoopData = $options; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $optionValue = is_array($option) ? (string)($option['value'] ?? $value) : (string)$value;
                $optionLabel = is_array($option) ? (string)($option['label'] ?? $optionValue) : (string)$option;
                $optionDisabled = is_array($option) && !empty($option['disabled']);
            ?>
            <option value="<?php echo e($optionValue); ?>"
                    <?php if(in_array($optionValue, $selectedValues, true)): echo 'selected'; endif; ?>
                    <?php if($optionDisabled): echo 'disabled'; endif; ?>>
                <?php echo e($optionLabel); ?>

            </option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
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
<?php /**PATH /var/www/html/resources/views/components/md/select.blade.php ENDPATH**/ ?>