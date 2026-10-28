<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconAfter' => null,
    'href' => null,
    'type' => 'button',
    'disabled' => false,
    'block' => false,
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
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconAfter' => null,
    'href' => null,
    'type' => 'button',
    'disabled' => false,
    'block' => false,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $variants = ['primary', 'secondary', 'outline', 'ghost', 'danger', 'success'];
    $sizes = ['sm', 'md', 'lg'];
    $variant = in_array($variant, $variants, true) ? $variant : 'primary';
    $size = in_array($size, $sizes, true) ? $size : 'md';
    $classes = array_filter([
        'md-btn',
        'md-btn--' . $variant,
        $size !== 'md' ? 'md-btn--' . $size : null,
        $block ? 'md-btn--block' : null,
        $disabled ? 'is-disabled' : null,
    ]);
?>

<?php if($href): ?>
    <a href="<?php echo e($disabled ? '#' : $href); ?>"
       role="button"
       <?php if($disabled): ?> aria-disabled="true" tabindex="-1" <?php endif; ?>
       <?php echo e($attributes->class($classes)); ?>>
        <?php if($icon): ?><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => $icon,'class' => 'md-btn__icon']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'md-btn__icon']); ?>
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
        <span class="md-btn__label"><?php echo e($slot); ?></span>
        <?php if($iconAfter): ?><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => $iconAfter,'class' => 'md-btn__icon md-btn__icon--after']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($iconAfter),'class' => 'md-btn__icon md-btn__icon--after']); ?>
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
    </a>
<?php else: ?>
    <button type="<?php echo e($type); ?>"
            <?php if($disabled): echo 'disabled'; endif; ?>
            <?php echo e($attributes->class($classes)); ?>>
        <?php if($icon): ?><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => $icon,'class' => 'md-btn__icon']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'md-btn__icon']); ?>
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
        <span class="md-btn__label"><?php echo e($slot); ?></span>
        <?php if($iconAfter): ?><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => $iconAfter,'class' => 'md-btn__icon md-btn__icon--after']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($iconAfter),'class' => 'md-btn__icon md-btn__icon--after']); ?>
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
    </button>
<?php endif; ?>
<?php /**PATH /var/www/html/resources/views/components/md/button.blade.php ENDPATH**/ ?>