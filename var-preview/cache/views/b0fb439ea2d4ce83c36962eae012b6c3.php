<!doctype html>
<html lang="ru" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Вход — LaIOT</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="stylesheet" href="/assets/admin/laiot.css">
    <link rel="stylesheet" href="/assets/auth/login.css">
    <script>
        (function () {
            var match = document.cookie.match(/(?:^|;\s*)theme=([^;]+)/);
            var theme = match ? decodeURIComponent(match[1]) : 'light';
            document.documentElement.setAttribute('data-bs-theme', theme === 'dark' ? 'dark' : 'light');
        })();
    </script>
</head>
<body class="md-admin-shell md-auth">
<div class="md-auth__theme" role="group" aria-label="Тема оформления">
    <input class="btn-check" type="radio" name="authTheme" id="authThemeLight" value="light" data-md-theme-choice="light">
    <label for="authThemeLight" title="Светлая тема"><i class="ti ti-sun" aria-hidden="true"></i><span class="visually-hidden">Светлая тема</span></label>
    <input class="btn-check" type="radio" name="authTheme" id="authThemeDark" value="dark" data-md-theme-choice="dark">
    <label for="authThemeDark" title="Тёмная тема"><i class="ti ti-moon-stars" aria-hidden="true"></i><span class="visually-hidden">Тёмная тема</span></label>
</div>

<main class="md-auth__main">
    <div class="md-auth__brand">
        <span class="md-logo">
            <img class="md-logo__light" src="/assets/img/logos/laiot-logo.png"
                 srcset="/assets/img/logos/laiot-logo@2x.png 2x"
                 width="333" height="101" alt="LaIOT" decoding="async">
            <img class="md-logo__dark" src="/assets/img/logos/laiot-logo-white.png"
                 srcset="/assets/img/logos/laiot-logo-white@2x.png 2x"
                 width="300" height="91" alt="LaIOT" decoding="async">
        </span>
    </div>

    <div class="md-auth__panel">
        <h1 class="md-auth__title">Вход в панель управления</h1>
        <p class="md-auth__subtitle">Дом, автоматизации и устройства — в одном окне.</p>

        <?php if (isset($component)) { $__componentOriginal0d0bf55a30a19bbc1b2fcfad6bcbefc0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0d0bf55a30a19bbc1b2fcfad6bcbefc0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.card','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
            <form method="post" action="/login" class="md-ui-form-stack">
                <input type="hidden" name="_token" value="<?php echo e($csrf); ?>">
                <?php if($error): ?>
                    <?php if (isset($component)) { $__componentOriginal31a8100678770fc6cebe956bc570585c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal31a8100678770fc6cebe956bc570585c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.hint','data' => ['tone' => 'danger','title' => 'Не удалось войти','text' => $error]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.hint'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['tone' => 'danger','title' => 'Не удалось войти','text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($error)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal31a8100678770fc6cebe956bc570585c)): ?>
<?php $attributes = $__attributesOriginal31a8100678770fc6cebe956bc570585c; ?>
<?php unset($__attributesOriginal31a8100678770fc6cebe956bc570585c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal31a8100678770fc6cebe956bc570585c)): ?>
<?php $component = $__componentOriginal31a8100678770fc6cebe956bc570585c; ?>
<?php unset($__componentOriginal31a8100678770fc6cebe956bc570585c); ?>
<?php endif; ?>
                <?php endif; ?>
                <?php if (isset($component)) { $__componentOriginalb886b7d9c7f257ca97615d7374fcf93f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb886b7d9c7f257ca97615d7374fcf93f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.input','data' => ['id' => 'username','name' => 'username','label' => 'Логин','value' => $username ?? '','autocomplete' => 'username','required' => true,'size' => 'lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'username','name' => 'username','label' => 'Логин','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($username ?? ''),'autocomplete' => 'username','required' => true,'size' => 'lg']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb886b7d9c7f257ca97615d7374fcf93f)): ?>
<?php $attributes = $__attributesOriginalb886b7d9c7f257ca97615d7374fcf93f; ?>
<?php unset($__attributesOriginalb886b7d9c7f257ca97615d7374fcf93f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb886b7d9c7f257ca97615d7374fcf93f)): ?>
<?php $component = $__componentOriginalb886b7d9c7f257ca97615d7374fcf93f; ?>
<?php unset($__componentOriginalb886b7d9c7f257ca97615d7374fcf93f); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginalb886b7d9c7f257ca97615d7374fcf93f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb886b7d9c7f257ca97615d7374fcf93f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.input','data' => ['id' => 'password','type' => 'password','name' => 'password','label' => 'Пароль','autocomplete' => 'current-password','required' => true,'size' => 'lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'password','type' => 'password','name' => 'password','label' => 'Пароль','autocomplete' => 'current-password','required' => true,'size' => 'lg']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb886b7d9c7f257ca97615d7374fcf93f)): ?>
<?php $attributes = $__attributesOriginalb886b7d9c7f257ca97615d7374fcf93f; ?>
<?php unset($__attributesOriginalb886b7d9c7f257ca97615d7374fcf93f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb886b7d9c7f257ca97615d7374fcf93f)): ?>
<?php $component = $__componentOriginalb886b7d9c7f257ca97615d7374fcf93f; ?>
<?php unset($__componentOriginalb886b7d9c7f257ca97615d7374fcf93f); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginale300843b4ce048894a92319c5215c17c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale300843b4ce048894a92319c5215c17c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.button','data' => ['type' => 'submit','icon' => 'login','size' => 'lg','class' => 'md-auth__submit','block' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','icon' => 'login','size' => 'lg','class' => 'md-auth__submit','block' => true]); ?>Войти <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $attributes = $__attributesOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__attributesOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $component = $__componentOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__componentOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
            </form>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0d0bf55a30a19bbc1b2fcfad6bcbefc0)): ?>
<?php $attributes = $__attributesOriginal0d0bf55a30a19bbc1b2fcfad6bcbefc0; ?>
<?php unset($__attributesOriginal0d0bf55a30a19bbc1b2fcfad6bcbefc0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0d0bf55a30a19bbc1b2fcfad6bcbefc0)): ?>
<?php $component = $__componentOriginal0d0bf55a30a19bbc1b2fcfad6bcbefc0; ?>
<?php unset($__componentOriginal0d0bf55a30a19bbc1b2fcfad6bcbefc0); ?>
<?php endif; ?>

        <p class="md-auth__foot"><i class="ti ti-shield-lock" aria-hidden="true"></i> Доступ только для администраторов системы.</p>
    </div>
</main>
<script src="/assets/admin/laiot.js" defer></script>
</body>
</html>
<?php /**PATH /var/www/html/resources/views/login.blade.php ENDPATH**/ ?>