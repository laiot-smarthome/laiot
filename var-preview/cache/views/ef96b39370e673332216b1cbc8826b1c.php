<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'type' => 'info',
    'title' => null,
    'text' => null,
    'actions' => null,
    'dismissible' => false,
    'icon' => null,
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
    'type' => 'info',
    'title' => null,
    'text' => null,
    'actions' => null,
    'dismissible' => false,
    'icon' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $types = ['info', 'success', 'warning', 'danger'];
    $type = in_array($type, $types, true) ? $type : 'info';
    $icons = [
        'info' => 'info-circle',
        'success' => 'circle-check',
        'warning' => 'alert-triangle',
        'danger' => 'circle-x',
    ];
    $icon = $icon ?? $icons[$type];
?>

<div <?php echo e($attributes->class(['md-alert', 'md-alert--' . $type])); ?>

     <?php if($dismissible): ?> x-data="{show: true}" x-show="show" <?php endif; ?>
     role="<?php echo e($type === 'danger' ? 'alert' : 'status'); ?>">
    <span class="md-alert__icon"><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => $icon]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon)]); ?>
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
    <div class="md-alert__body">
        <?php if($title !== null): ?><div class="md-alert__title"><?php echo e($title); ?></div><?php endif; ?>
        <?php if($text !== null): ?><div class="md-alert__text"><?php echo e($text); ?></div><?php endif; ?>
        <?php if($actions !== null): ?><div class="md-alert__actions"><?php echo $actions; ?></div><?php endif; ?>
    </div>
    <?php if($dismissible): ?>
        <button type="button" class="md-alert__close" @click="show = false" aria-label="Закрыть">
            <?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => 'x-lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'x-lg']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8)): ?>
<?php $attributes = $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8; ?>
<?php unset($__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8)): ?>
<?php $component = $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8; ?>
<?php unset($__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8); ?>
<?php endif; ?>
        </button>
    <?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/components/md/alert.blade.php ENDPATH**/ ?>