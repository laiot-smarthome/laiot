<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['pose' => 'hello']));

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

foreach (array_filter((['pose' => 'hello']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $poses = ['drink', 'error', 'find', 'nothing', 'hello', 'hide', 'idea', 'joy', 'love_home', 'love', 'message', 'music', 'quest', 'read', 'sleep', 'success', 'todo', 'work', 'wow'];
    if (!in_array($pose, $poses, true)) {
        throw new \InvalidArgumentException('Неизвестная поза маскота.');
    }
?>

<img <?php echo e($attributes->class(['md-mascot'])); ?> src="/assets/img/maskot/<?php echo e($pose); ?>.png" alt="" width="250" height="250" decoding="async">
<?php /**PATH /var/www/html/resources/views/components/md/mascot.blade.php ENDPATH**/ ?>