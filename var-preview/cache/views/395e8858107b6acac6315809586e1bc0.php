<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'title' => null,
    'subtitle' => null,
    'eyebrow' => null,
    'icon' => null,
    'actions' => null,
    'footer' => null,
    'flush' => false,
    'compact' => false,
    'interactive' => false,
    'headingLevel' => 3,
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
    'title' => null,
    'subtitle' => null,
    'eyebrow' => null,
    'icon' => null,
    'actions' => null,
    'footer' => null,
    'flush' => false,
    'compact' => false,
    'interactive' => false,
    'headingLevel' => 3,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $headingLevel = in_array((int)$headingLevel, [1, 2, 3, 4], true) ? (int)$headingLevel : 3;
    $hasHead = $title !== null || $subtitle !== null || $eyebrow !== null || $actions !== null;
    $classes = array_filter([
        'md-card',
        $compact ? 'md-card--compact' : null,
        $interactive ? 'md-card--interactive' : null,
    ]);
?>

<section <?php echo e($attributes->class($classes)); ?>>
    <?php if($hasHead): ?>
        <header class="md-card__head">
            <div class="md-card__titles">
                <?php if($eyebrow !== null): ?><div class="md-card__eyebrow"><?php echo e($eyebrow); ?></div><?php endif; ?>
                <?php if($title !== null): ?>
                    <h<?php echo e($headingLevel); ?> class="md-card__title">
                        <?php if($icon): ?><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => $icon,'class' => 'md-card__title-icon']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'md-card__title-icon']); ?>
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
                        <?php echo e($title); ?>

                    </h<?php echo e($headingLevel); ?>>
                <?php endif; ?>
                <?php if($subtitle !== null): ?><p class="md-card__subtitle"><?php echo e($subtitle); ?></p><?php endif; ?>
            </div>
            <?php if($actions !== null): ?><div class="md-card__actions"><?php echo $actions; ?></div><?php endif; ?>
        </header>
    <?php endif; ?>

    <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['md-card__body', 'md-card__body--flush' => $flush]); ?>"><?php echo e($slot); ?></div>

    <?php if($footer !== null): ?>
        <footer class="md-card__footer"><?php echo $footer; ?></footer>
    <?php endif; ?>
</section>
<?php /**PATH /var/www/html/resources/views/components/md/card.blade.php ENDPATH**/ ?>