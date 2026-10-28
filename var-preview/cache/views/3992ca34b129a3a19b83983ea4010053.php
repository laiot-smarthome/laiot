<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'label' => null,
    'description' => null,
    'name' => null,
    'id' => null,
    'value' => '1',
    'checked' => false,
    'disabled' => false,
    'required' => false,
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
    'label' => null,
    'description' => null,
    'name' => null,
    'id' => null,
    'value' => '1',
    'checked' => false,
    'disabled' => false,
    'required' => false,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $id = $id ?? 'md-check-' . substr(md5(($name ?? '') . '|' . ($label ?? '') . '|' . $value), 0, 8);
?>

<label class="md-check" for="<?php echo e($id); ?>">
    <input type="checkbox"
           data-md-raw-checkbox
           id="<?php echo e($id); ?>"
           <?php echo e($attributes->class(['md-check__input'])); ?>

           <?php if($name !== null): ?> name="<?php echo e($name); ?>" <?php endif; ?>
           value="<?php echo e($value); ?>"
           <?php if($checked): echo 'checked'; endif; ?>
           <?php if($disabled): echo 'disabled'; endif; ?>
           <?php if($required): echo 'required'; endif; ?>>
    <span class="md-check__box" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => 'check']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'check']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8)): ?>
<?php $attributes = $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8; ?>
<?php unset($__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8)): ?>
<?php $component = $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8; ?>
<?php unset($__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8); ?>
<?php endif; ?></span>
    <?php if($label !== null || $description !== null): ?>
        <span class="md-check__text">
            <?php if($label !== null): ?><span class="md-check__label"><?php echo e($label); ?></span><?php endif; ?>
            <?php if($description !== null): ?><span class="md-check__desc"><?php echo e($description); ?></span><?php endif; ?>
        </span>
    <?php endif; ?>
</label>
<?php /**PATH /var/www/html/resources/views/components/md/checkbox.blade.php ENDPATH**/ ?>