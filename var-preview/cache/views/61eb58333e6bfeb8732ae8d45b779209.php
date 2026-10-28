<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'label' => null,
    'for' => null,
    'description' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'optional' => false,
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
    'for' => null,
    'description' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'optional' => false,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $hasError = $error !== null;
    $help = $error ?? $hint ?? $description;
    $helpId = $for !== null ? $for . '-help' : null;
?>

<div <?php echo e($attributes->class(['md-field', 'is-invalid' => $hasError])); ?>>
    <?php if($label !== null): ?>
        <label class="md-field__label" <?php if($for !== null): ?> for="<?php echo e($for); ?>" <?php endif; ?>>
            <?php echo e($label); ?>

            <?php if($required): ?><span class="md-field__req" aria-hidden="true">*</span><?php endif; ?>
            <?php if($optional): ?><span class="md-field__optional">необязательно</span><?php endif; ?>
        </label>
    <?php endif; ?>

    <div class="md-field__control"><?php echo e($slot); ?></div>

    <?php if($help !== null): ?>
        <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['md-field__error' => $hasError, 'md-field__hint' => !$hasError]); ?>"
             <?php if($hasError): ?> role="alert" <?php endif; ?>
             <?php if($helpId !== null): ?> id="<?php echo e($helpId); ?>" <?php endif; ?>>
            <?php if($hasError): ?><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => 'alert-circle']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'alert-circle']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8)): ?>
<?php $attributes = $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8; ?>
<?php unset($__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8)): ?>
<?php $component = $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8; ?>
<?php unset($__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8); ?>
<?php endif; ?><?php endif; ?>
            <span><?php echo e($help); ?></span>
        </div>
    <?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/components/md/field.blade.php ENDPATH**/ ?>