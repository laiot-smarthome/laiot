
<?php
    $assetVersion = static function (string $path): string {
        $root = \LaIOT\Support\AppContext::get()?->root();
        $file = $root !== null ? $root . '/public' . $path : '';
        return $file !== '' && is_file($file) ? '?v=' . filemtime($file) : '';
    };
    $stepTitles = [1 => 'Знакомство', 2 => 'Сервер', 3 => 'База', 4 => 'Хозяин'];
    $base = '/install?key=' . urlencode($token ?? '');
    $icon = static fn (string $status): string => match ($status) {
        'ok' => 'circle-check',
        'warn' => 'alert-triangle',
        default => 'circle-x',
    };
    $checkTitle = static fn (string $status): string => match ($status) {
        'ok' => 'Готово',
        'warn' => 'Можно дальше',
        default => 'Нужно исправить',
    };
    $timezones = timezone_identifiers_list();
?>
<!doctype html>
<html lang="ru" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Установка — LaIOT</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="stylesheet" href="/assets/admin/laiot.css<?php echo e($assetVersion('/assets/admin/laiot.css')); ?>">
    <link rel="stylesheet" href="/assets/install/setup.css<?php echo e($assetVersion('/assets/install/setup.css')); ?>">
    <script>
        (function () {
            var match = document.cookie.match(/(?:^|;\s*)theme=([^;]+)/);
            var theme = match ? decodeURIComponent(match[1]) : 'light';
            document.documentElement.setAttribute('data-bs-theme', theme === 'dark' ? 'dark' : 'light');
        })();
    </script>
</head>
<body class="md-admin-shell md-setup">
<div class="md-setup__theme" role="group" aria-label="Тема оформления">
    <input class="btn-check" type="radio" name="installTheme" id="installThemeLight" value="light" data-md-theme-choice="light">
    <label for="installThemeLight" title="Светлая тема"><i class="ti ti-sun" aria-hidden="true"></i><span class="visually-hidden">Светлая тема</span></label>
    <input class="btn-check" type="radio" name="installTheme" id="installThemeDark" value="dark" data-md-theme-choice="dark">
    <label for="installThemeDark" title="Тёмная тема"><i class="ti ti-moon-stars" aria-hidden="true"></i><span class="visually-hidden">Тёмная тема</span></label>
</div>

<main class="md-setup__main">
    <header class="md-setup__top">
        <a class="md-setup__brand" href="/install" aria-label="LaIOT — установка">
            <span class="md-logo">
                <img class="md-logo__light" src="/assets/img/logos/laiot-logo.png"
                     srcset="/assets/img/logos/laiot-logo@2x.png 2x"
                     width="333" height="101" alt="LaIOT" decoding="async">
                <img class="md-logo__dark" src="/assets/img/logos/laiot-logo-white.png"
                     srcset="/assets/img/logos/laiot-logo-white@2x.png 2x"
                     width="300" height="91" alt="LaIOT" decoding="async">
            </span>
        </a>
        <span class="md-setup__badge">Первый запуск · <?php echo e($version); ?></span>
    </header>

    <?php if($state === 'unavailable'): ?>
        <section class="md-setup__panel md-setup__panel--center">
            <?php if (isset($component)) { $__componentOriginal80a7e94fc1239503a70259f9979d457c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal80a7e94fc1239503a70259f9979d457c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.mascot','data' => ['pose' => 'nothing','class' => 'md-setup__mascot md-setup__mascot--small']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.mascot'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['pose' => 'nothing','class' => 'md-setup__mascot md-setup__mascot--small']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal80a7e94fc1239503a70259f9979d457c)): ?>
<?php $attributes = $__attributesOriginal80a7e94fc1239503a70259f9979d457c; ?>
<?php unset($__attributesOriginal80a7e94fc1239503a70259f9979d457c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal80a7e94fc1239503a70259f9979d457c)): ?>
<?php $component = $__componentOriginal80a7e94fc1239503a70259f9979d457c; ?>
<?php unset($__componentOriginal80a7e94fc1239503a70259f9979d457c); ?>
<?php endif; ?>
            <h1 class="md-setup__title">Почти получилось</h1>
            <p class="md-setup__lead">
                LaIOT не может создать папку <code>var</code> или записать в неё ключ.
                Это лечится правами — данные и файлы не пострадали.
            </p>
            <ol class="md-setup__steps">
                <li>В FastPanel открой «Файлы» и найди каталог установки.</li>
                <li>Дай пользователю сайта запись в каталог <code>var</code> и в сам каталог установки.</li>
                <li>Обнови страницу — ключ появится сам.</li>
            </ol>
            <?php if (isset($component)) { $__componentOriginale300843b4ce048894a92319c5215c17c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale300843b4ce048894a92319c5215c17c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.button','data' => ['type' => 'button','icon' => 'refresh','onclick' => 'location.reload()']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','icon' => 'refresh','onclick' => 'location.reload()']); ?>Проверить снова <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $attributes = $__attributesOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__attributesOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $component = $__componentOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__componentOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
        </section>
    <?php elseif($state === 'key'): ?>
        <section class="md-setup__panel md-setup__panel--center">
            <?php if (isset($component)) { $__componentOriginal80a7e94fc1239503a70259f9979d457c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal80a7e94fc1239503a70259f9979d457c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.mascot','data' => ['pose' => 'quest','class' => 'md-setup__mascot md-setup__mascot--small']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.mascot'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['pose' => 'quest','class' => 'md-setup__mascot md-setup__mascot--small']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal80a7e94fc1239503a70259f9979d457c)): ?>
<?php $attributes = $__attributesOriginal80a7e94fc1239503a70259f9979d457c; ?>
<?php unset($__attributesOriginal80a7e94fc1239503a70259f9979d457c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal80a7e94fc1239503a70259f9979d457c)): ?>
<?php $component = $__componentOriginal80a7e94fc1239503a70259f9979d457c; ?>
<?php unset($__componentOriginal80a7e94fc1239503a70259f9979d457c); ?>
<?php endif; ?>
            <h1 class="md-setup__title">Остался ключ</h1>
            <p class="md-setup__lead">
                Установщик заперт одноразовым ключом — так его не откроет кто-то чужой.
                Ключ уже лежит в файле <code>var/install.key</code>: открой его в файловом
                менеджере FastPanel или прочитай по SSH.
            </p>
            <?php if($keyError !== null): ?>
                <?php if (isset($component)) { $__componentOriginal67ec6f84581691906d10e0e28720d229 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal67ec6f84581691906d10e0e28720d229 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.alert','data' => ['type' => 'danger','title' => 'Ключ не подошёл','text' => $keyError]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'danger','title' => 'Ключ не подошёл','text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($keyError)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $attributes = $__attributesOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__attributesOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $component = $__componentOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__componentOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
            <?php endif; ?>
            <form method="get" action="/install" class="md-setup__key-form">
                <?php if (isset($component)) { $__componentOriginalb886b7d9c7f257ca97615d7374fcf93f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb886b7d9c7f257ca97615d7374fcf93f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.input','data' => ['id' => 'install-key','name' => 'key','label' => 'Ключ из файла','autocomplete' => 'off','required' => true,'size' => 'lg','hint' => '64 символа: буквы и цифры']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'install-key','name' => 'key','label' => 'Ключ из файла','autocomplete' => 'off','required' => true,'size' => 'lg','hint' => '64 символа: буквы и цифры']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.button','data' => ['type' => 'submit','icon' => 'arrow-right','size' => 'lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','icon' => 'arrow-right','size' => 'lg']); ?>Открыть установщик <?php echo $__env->renderComponent(); ?>
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
            <p class="md-setup__micro">После установки ключ удалится — страница закроется сама.</p>
        </section>
    <?php elseif($state === 'done'): ?>
        <section class="md-setup__panel md-setup__panel--done">
            <div class="md-setup__done-hero">
                <?php if (isset($component)) { $__componentOriginal80a7e94fc1239503a70259f9979d457c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal80a7e94fc1239503a70259f9979d457c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.mascot','data' => ['pose' => 'success','class' => 'md-setup__mascot']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.mascot'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['pose' => 'success','class' => 'md-setup__mascot']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal80a7e94fc1239503a70259f9979d457c)): ?>
<?php $attributes = $__attributesOriginal80a7e94fc1239503a70259f9979d457c; ?>
<?php unset($__attributesOriginal80a7e94fc1239503a70259f9979d457c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal80a7e94fc1239503a70259f9979d457c)): ?>
<?php $component = $__componentOriginal80a7e94fc1239503a70259f9979d457c; ?>
<?php unset($__componentOriginal80a7e94fc1239503a70259f9979d457c); ?>
<?php endif; ?>
                <span class="md-setup__spark" style="--x: 8%; --d: .1s"></span>
                <span class="md-setup__spark" style="--x: 22%; --d: .35s"></span>
                <span class="md-setup__spark" style="--x: 74%; --d: .2s"></span>
                <span class="md-setup__spark" style="--x: 88%; --d: .5s"></span>
            </div>
            <h1 class="md-setup__title">Дом готов!</h1>
            <p class="md-setup__lead">
                LaIOT <?php echo e($report->version); ?> установлена. Теперь можно войти и добавить
                первый этаж, комнату и устройства.
            </p>

            <?php $__currentLoopData = $report->warnings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $warning): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if (isset($component)) { $__componentOriginal67ec6f84581691906d10e0e28720d229 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal67ec6f84581691906d10e0e28720d229 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.alert','data' => ['type' => 'warning','text' => $warning]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'warning','text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($warning)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $attributes = $__attributesOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__attributesOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $component = $__componentOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__componentOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <div class="md-setup__summary">
                <div class="md-setup__summary-item">
                    <span class="md-setup__summary-label">Хозяин дома</span>
                    <strong><?php echo e($report->adminLogin); ?></strong>
                </div>
                <div class="md-setup__summary-item">
                    <span class="md-setup__summary-label">База и кэш</span>
                    <strong><?php echo e($report->cacheDriver === 'redis' ? 'Redis' : 'Файлы'); ?></strong>
                </div>
                <div class="md-setup__summary-item">
                    <span class="md-setup__summary-label">Миграций</span>
                    <strong><?php echo e($report->migrationsCount()); ?></strong>
                </div>
            </div>

            <div class="md-setup__next">
                <h2>Что дальше</h2>
                <ol>
                    <li>Войди в панель — кнопка ниже.</li>
                    <li>Запусти фоновые службы <code>queue</code>, <code>events</code> и <code>timeouts</code>:
                        без них не работают история и автоматизации. Инструкция — <code>docs/laiot/deploy.md</code>.</li>
                    <li>Добавь этаж, комнату и первое устройство.</li>
                </ol>
            </div>

            <div class="md-setup__actions md-setup__actions--center">
                <?php if (isset($component)) { $__componentOriginale300843b4ce048894a92319c5215c17c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale300843b4ce048894a92319c5215c17c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.button','data' => ['href' => ''.e($login).'','icon' => 'login','size' => 'lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e($login).'','icon' => 'login','size' => 'lg']); ?>Войти в панель <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $attributes = $__attributesOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__attributesOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $component = $__componentOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__componentOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
            </div>
        </section>
    <?php else: ?>
        <div class="md-setup__wizard">
            <ol class="md-setup__rail" aria-label="Шаги установки">
                <?php $__currentLoopData = $stepTitles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $number => $title): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                        'md-setup__rail-item',
                        'is-done' => $step > $number,
                        'is-active' => $step === $number,
                    ]); ?>">
                        <span class="md-setup__rail-dot">
                            <?php if($step > $number): ?>
                                <?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
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
<?php endif; ?>
                            <?php else: ?>
                                <?php echo e($number); ?>

                            <?php endif; ?>
                        </span>
                        <span class="md-setup__rail-label"><?php echo e($title); ?></span>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ol>

            <section class="md-setup__panel md-setup__panel--step-<?php echo e($step); ?>"
                     data-step="<?php echo e($step); ?>">
                <?php if($step === 1): ?>
                    <div class="md-setup__hero">
                        <div class="md-setup__hero-copy">
                            <span class="md-setup__eyebrow">Первый запуск</span>
                            <h1 class="md-setup__title">Привет! Давай оживим дом</h1>
                            <p class="md-setup__lead">
                                LaIOT — локальный умный дом: сценарии, устройства, история
                                и автоматизации. Всё живёт на твоём сервере, без облака
                                и подписок.
                            </p>
                            <ul class="md-setup__chips">
                                <li><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => 'home']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'home']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8)): ?>
<?php $attributes = $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8; ?>
<?php unset($__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8)): ?>
<?php $component = $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8; ?>
<?php unset($__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8); ?>
<?php endif; ?> Свой дом</li>
                                <li><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => 'sparkles']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'sparkles']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8)): ?>
<?php $attributes = $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8; ?>
<?php unset($__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8)): ?>
<?php $component = $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8; ?>
<?php unset($__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8); ?>
<?php endif; ?> Сценарии</li>
                                <li><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => 'shield-lock']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'shield-lock']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8)): ?>
<?php $attributes = $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8; ?>
<?php unset($__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8)): ?>
<?php $component = $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8; ?>
<?php unset($__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8); ?>
<?php endif; ?> Только локально</li>
                            </ul>
                            <form method="post" action="/install" class="md-setup__cta">
                                <input type="hidden" name="_token" value="<?php echo e($csrf); ?>">
                                <input type="hidden" name="key" value="<?php echo e($token); ?>">
                                <input type="hidden" name="stage" value="checks">
                                <?php if (isset($component)) { $__componentOriginale300843b4ce048894a92319c5215c17c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale300843b4ce048894a92319c5215c17c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.button','data' => ['type' => 'submit','icon' => 'arrow-right','size' => 'lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','icon' => 'arrow-right','size' => 'lg']); ?>Начать настройку <?php echo $__env->renderComponent(); ?>
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
                            <p class="md-setup__micro">Пара минут. Сначала всё проверим — ничего не сломаем.</p>
                        </div>
                        <div class="md-setup__hero-art">
                            <span class="md-setup__glow" aria-hidden="true"></span>
                            <?php if (isset($component)) { $__componentOriginal80a7e94fc1239503a70259f9979d457c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal80a7e94fc1239503a70259f9979d457c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.mascot','data' => ['pose' => 'hello','class' => 'md-setup__mascot']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.mascot'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['pose' => 'hello','class' => 'md-setup__mascot']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal80a7e94fc1239503a70259f9979d457c)): ?>
<?php $attributes = $__attributesOriginal80a7e94fc1239503a70259f9979d457c; ?>
<?php unset($__attributesOriginal80a7e94fc1239503a70259f9979d457c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal80a7e94fc1239503a70259f9979d457c)): ?>
<?php $component = $__componentOriginal80a7e94fc1239503a70259f9979d457c; ?>
<?php unset($__componentOriginal80a7e94fc1239503a70259f9979d457c); ?>
<?php endif; ?>
                        </div>
                    </div>

                    <div class="md-setup__features">
                        <article class="md-setup__feature">
                            <span class="md-setup__feature-icon"><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => 'home']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'home']); ?>
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
                            <h2>Дом как есть</h2>
                            <p>Этажи, комнаты и объекты — понятная структура, а не свалка устройств.</p>
                        </article>
                        <article class="md-setup__feature">
                            <span class="md-setup__feature-icon"><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => 'sparkles']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'sparkles']); ?>
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
                            <h2>Сценарии</h2>
                            <p>Свет по движению, климат по времени, шторы по солнцу.</p>
                        </article>
                        <article class="md-setup__feature">
                            <span class="md-setup__feature-icon"><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => 'plug-connected']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'plug-connected']); ?>
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
                            <h2>Устройства</h2>
                            <p>MQTT, Zigbee2MQTT, датчики и реле — в одном месте.</p>
                        </article>
                        <article class="md-setup__feature">
                            <span class="md-setup__feature-icon"><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => 'history']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'history']); ?>
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
                            <h2>История</h2>
                            <p>Графики и журнал: видно, что случилось и почему.</p>
                        </article>
                    </div>

                    <p class="md-setup__plan">
                        <strong>Дальше:</strong> проверим сервер → подключим базу → создадим хозяина дома.
                    </p>
                <?php elseif($step === 2): ?>
                    <header class="md-setup__head">
                        <span class="md-setup__eyebrow">Шаг 2 из 4</span>
                        <h1 class="md-setup__title">Проверим сервер</h1>
                        <p class="md-setup__lead">Смотрим, всё ли готово для LaIOT. Ничего не меняем — только читаем.</p>
                    </header>

                    <?php if($blocked): ?>
                        <?php if (isset($component)) { $__componentOriginal67ec6f84581691906d10e0e28720d229 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal67ec6f84581691906d10e0e28720d229 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.alert','data' => ['type' => 'danger','title' => 'Пока нельзя продолжить','text' => 'Исправь отмеченное и обнови страницу. Без этого установка не пойдёт.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'danger','title' => 'Пока нельзя продолжить','text' => 'Исправь отмеченное и обнови страницу. Без этого установка не пойдёт.']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $attributes = $__attributesOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__attributesOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $component = $__componentOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__componentOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
                    <?php else: ?>
                        <?php if (isset($component)) { $__componentOriginal67ec6f84581691906d10e0e28720d229 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal67ec6f84581691906d10e0e28720d229 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.alert','data' => ['type' => 'success','title' => 'Всё готово','text' => 'Сервер примет LaIOT. Можно продолжать.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'success','title' => 'Всё готово','text' => 'Сервер примет LaIOT. Можно продолжать.']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $attributes = $__attributesOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__attributesOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $component = $__componentOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__componentOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
                    <?php endif; ?>

                    <ul class="md-setup__checks">
                        <?php $__currentLoopData = $checks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $check): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="<?php echo \Illuminate\Support\Arr::toCssClasses(['md-setup__check', 'md-setup__check--' . $check['status']]); ?>"
                                style="--i: <?php echo e($loop->index); ?>">
                                <span class="md-setup__check-icon"><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => $icon($check['status'])]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon($check['status']))]); ?>
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
                                <span class="md-setup__check-body">
                                    <span class="md-setup__check-label"><?php echo e($check['label']); ?></span>
                                    <?php if($check['message'] !== ''): ?>
                                        <span class="md-setup__check-text"><?php echo e($check['message']); ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="md-setup__check-state"><?php echo e($checkTitle($check['status'])); ?></span>
                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>

                    <div class="md-setup__actions">
                        <?php if (isset($component)) { $__componentOriginale300843b4ce048894a92319c5215c17c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale300843b4ce048894a92319c5215c17c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.button','data' => ['href' => ''.e($base).'','variant' => 'ghost','icon' => 'arrow-left']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e($base).'','variant' => 'ghost','icon' => 'arrow-left']); ?>Назад <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $attributes = $__attributesOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__attributesOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $component = $__componentOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__componentOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
                        <a class="md-setup__link" href="<?php echo e($base); ?>&step=2">Проверить снова</a>
                        <form method="post" action="/install" class="md-setup__inline">
                            <input type="hidden" name="_token" value="<?php echo e($csrf); ?>">
                            <input type="hidden" name="key" value="<?php echo e($token); ?>">
                            <input type="hidden" name="stage" value="connect">
                            <?php if (isset($component)) { $__componentOriginale300843b4ce048894a92319c5215c17c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale300843b4ce048894a92319c5215c17c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.button','data' => ['type' => 'submit','icon' => 'arrow-right','disabled' => $blocked]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','icon' => 'arrow-right','disabled' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($blocked)]); ?>Продолжить <?php echo $__env->renderComponent(); ?>
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
                    </div>
                <?php elseif($step === 3): ?>
                    <header class="md-setup__head">
                        <span class="md-setup__eyebrow">Шаг 3 из 4</span>
                        <h1 class="md-setup__title">Подключим базу</h1>
                        <p class="md-setup__lead">
                            Здесь будут жить дом, сценарии и история. Пароль сохранится
                            в <code>.env</code> на сервере и не вернётся в браузер.
                        </p>
                    </header>

                    <?php if(isset($errors['global'])): ?>
                        <?php if (isset($component)) { $__componentOriginal67ec6f84581691906d10e0e28720d229 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal67ec6f84581691906d10e0e28720d229 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.alert','data' => ['type' => 'danger','title' => 'Не получилось продолжить','text' => $errors['global']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'danger','title' => 'Не получилось продолжить','text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors['global'])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $attributes = $__attributesOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__attributesOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $component = $__componentOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__componentOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
                    <?php elseif($errors !== []): ?>
                        <?php if (isset($component)) { $__componentOriginal67ec6f84581691906d10e0e28720d229 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal67ec6f84581691906d10e0e28720d229 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.alert','data' => ['type' => 'danger','title' => 'Проверь поля','text' => 'Пока есть ошибки в подключении, дальше не пойдём.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'danger','title' => 'Проверь поля','text' => 'Пока есть ошибки в подключении, дальше не пойдём.']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $attributes = $__attributesOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__attributesOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $component = $__componentOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__componentOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
                    <?php endif; ?>

                    <?php if($preflight !== []): ?>
                        <ul class="md-setup__checks md-setup__checks--compact">
                            <?php $__currentLoopData = $preflight; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $check): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li class="<?php echo \Illuminate\Support\Arr::toCssClasses(['md-setup__check', 'md-setup__check--' . $check['status']]); ?>"
                                    style="--i: <?php echo e($loop->index); ?>">
                                    <span class="md-setup__check-icon"><?php if (isset($component)) { $__componentOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9f4ab3091d36a1bbf302c49bb8f1f8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.icon','data' => ['name' => $icon($check['status'])]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon($check['status']))]); ?>
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
                                    <span class="md-setup__check-body">
                                        <span class="md-setup__check-label"><?php echo e($check['label']); ?></span>
                                        <?php if($check['message'] !== ''): ?>
                                            <span class="md-setup__check-text"><?php echo e($check['message']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    <?php endif; ?>

                    <?php $__currentLoopData = $notices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if (isset($component)) { $__componentOriginal67ec6f84581691906d10e0e28720d229 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal67ec6f84581691906d10e0e28720d229 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.alert','data' => ['type' => 'success','text' => $notice]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'success','text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($notice)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $attributes = $__attributesOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__attributesOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $component = $__componentOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__componentOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                    <form method="post" action="/install" class="md-setup__form">
                        <input type="hidden" name="_token" value="<?php echo e($csrf); ?>">
                        <input type="hidden" name="key" value="<?php echo e($token); ?>">
                        <input type="hidden" name="stage" value="connection">

                        <fieldset class="md-setup__group">
                            <legend>База данных</legend>
                            <p class="md-setup__group-lead">
                                MySQL или MariaDB. Если база уже создана в FastPanel — просто укажи её.
                            </p>
                            <div class="md-setup__grid">
                                <?php if (isset($component)) { $__componentOriginalb886b7d9c7f257ca97615d7374fcf93f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb886b7d9c7f257ca97615d7374fcf93f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.input','data' => ['id' => 'db-host','name' => 'db_host','label' => 'Сервер','value' => $values['db_host'],'error' => $errors['db_host'] ?? null,'required' => true,'description' => 'Обычно 127.0.0.1 — там, где живёт база.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'db-host','name' => 'db_host','label' => 'Сервер','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($values['db_host']),'error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors['db_host'] ?? null),'required' => true,'description' => 'Обычно 127.0.0.1 — там, где живёт база.']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.input','data' => ['id' => 'db-port','name' => 'db_port','type' => 'number','label' => 'Порт','value' => $values['db_port'],'error' => $errors['db_port'] ?? null,'required' => true,'description' => 'Стандартный — 3306.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'db-port','name' => 'db_port','type' => 'number','label' => 'Порт','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($values['db_port']),'error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors['db_port'] ?? null),'required' => true,'description' => 'Стандартный — 3306.']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.input','data' => ['id' => 'db-name','name' => 'db_name','label' => 'Имя базы','value' => $values['db_name'],'error' => $errors['db_name'] ?? null,'required' => true,'description' => 'Можно указать готовую или создать новую.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'db-name','name' => 'db_name','label' => 'Имя базы','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($values['db_name']),'error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors['db_name'] ?? null),'required' => true,'description' => 'Можно указать готовую или создать новую.']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.input','data' => ['id' => 'db-user','name' => 'db_user','label' => 'Пользователь','value' => $values['db_user'],'error' => $errors['db_user'] ?? null,'required' => true,'description' => 'Отдельный пользователь LaIOT, не root.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'db-user','name' => 'db_user','label' => 'Пользователь','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($values['db_user']),'error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors['db_user'] ?? null),'required' => true,'description' => 'Отдельный пользователь LaIOT, не root.']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.input','data' => ['id' => 'db-password','name' => 'db_password','type' => 'password','label' => 'Пароль','value' => $values['db_password'],'error' => $errors['db_password'] ?? null,'autocomplete' => 'new-password','description' => $values['password_saved']
                                                ? 'Оставь пустым — сохранённый пароль не изменится.'
                                                : 'Пароль из FastPanel. Хранится только в .env на сервере.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'db-password','name' => 'db_password','type' => 'password','label' => 'Пароль','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($values['db_password']),'error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors['db_password'] ?? null),'autocomplete' => 'new-password','description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($values['password_saved']
                                                ? 'Оставь пустым — сохранённый пароль не изменится.'
                                                : 'Пароль из FastPanel. Хранится только в .env на сервере.')]); ?>
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
                            </div>
                            <?php if (isset($component)) { $__componentOriginalaf46d50525b11b433620cd19c6342f5d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalaf46d50525b11b433620cd19c6342f5d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.checkbox','data' => ['name' => 'db_create','label' => 'Создать базу, если её нет','description' => 'Нужны права на создание. Если база уже есть в панели — сними галочку.','checked' => $values['db_create']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.checkbox'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'db_create','label' => 'Создать базу, если её нет','description' => 'Нужны права на создание. Если база уже есть в панели — сними галочку.','checked' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($values['db_create'])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalaf46d50525b11b433620cd19c6342f5d)): ?>
<?php $attributes = $__attributesOriginalaf46d50525b11b433620cd19c6342f5d; ?>
<?php unset($__attributesOriginalaf46d50525b11b433620cd19c6342f5d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalaf46d50525b11b433620cd19c6342f5d)): ?>
<?php $component = $__componentOriginalaf46d50525b11b433620cd19c6342f5d; ?>
<?php unset($__componentOriginalaf46d50525b11b433620cd19c6342f5d); ?>
<?php endif; ?>
                        </fieldset>

                        <fieldset class="md-setup__group">
                            <legend>Память и очередь</legend>
                            <p class="md-setup__group-lead">
                                Redis делает фоновые задачи быстрее и не даёт им дублироваться.
                                Файлы подойдут, если Redis пока нет.
                            </p>
                            <div class="md-setup__grid">
                                <?php if (isset($component)) { $__componentOriginal50a282dab5e65725bdbf885f265e7fd4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal50a282dab5e65725bdbf885f265e7fd4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.select','data' => ['id' => 'cache-driver','name' => 'cache_driver','label' => 'Где хранить кэш','options' => ['redis' => 'Redis', 'file' => 'Файлы'],'selected' => $values['cache_driver'],'error' => $errors['cache_driver'] ?? null,'description' => 'Redis — для рабочего сервера, файлы — для одной машины.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'cache-driver','name' => 'cache_driver','label' => 'Где хранить кэш','options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['redis' => 'Redis', 'file' => 'Файлы']),'selected' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($values['cache_driver']),'error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors['cache_driver'] ?? null),'description' => 'Redis — для рабочего сервера, файлы — для одной машины.']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal50a282dab5e65725bdbf885f265e7fd4)): ?>
<?php $attributes = $__attributesOriginal50a282dab5e65725bdbf885f265e7fd4; ?>
<?php unset($__attributesOriginal50a282dab5e65725bdbf885f265e7fd4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal50a282dab5e65725bdbf885f265e7fd4)): ?>
<?php $component = $__componentOriginal50a282dab5e65725bdbf885f265e7fd4; ?>
<?php unset($__componentOriginal50a282dab5e65725bdbf885f265e7fd4); ?>
<?php endif; ?>
                            </div>
                            <div class="md-setup__grid">
                                <?php if (isset($component)) { $__componentOriginalb886b7d9c7f257ca97615d7374fcf93f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb886b7d9c7f257ca97615d7374fcf93f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.input','data' => ['id' => 'redis-host','name' => 'redis_host','label' => 'Сервер Redis','value' => $values['redis_host'],'error' => $errors['redis_host'] ?? null,'description' => 'Обычно 127.0.0.1.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'redis-host','name' => 'redis_host','label' => 'Сервер Redis','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($values['redis_host']),'error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors['redis_host'] ?? null),'description' => 'Обычно 127.0.0.1.']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.input','data' => ['id' => 'redis-port','name' => 'redis_port','type' => 'number','label' => 'Порт Redis','value' => $values['redis_port'],'error' => $errors['redis_port'] ?? null,'description' => 'Стандартный — 6379.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'redis-port','name' => 'redis_port','type' => 'number','label' => 'Порт Redis','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($values['redis_port']),'error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors['redis_port'] ?? null),'description' => 'Стандартный — 6379.']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.input','data' => ['id' => 'redis-database','name' => 'redis_database','type' => 'number','label' => 'База Redis','value' => $values['redis_database'],'error' => $errors['redis_database'] ?? null,'description' => '0–15. Лучше отдельная, не 0.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'redis-database','name' => 'redis_database','type' => 'number','label' => 'База Redis','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($values['redis_database']),'error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors['redis_database'] ?? null),'description' => '0–15. Лучше отдельная, не 0.']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.input','data' => ['id' => 'redis-password','name' => 'redis_password','type' => 'password','label' => 'Пароль Redis','value' => $values['redis_password'],'autocomplete' => 'new-password','description' => $values['password_saved']
                                                ? 'Оставь пустым — сохранённый пароль не изменится.'
                                                : 'Если Redis без пароля — оставь пустым.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'redis-password','name' => 'redis_password','type' => 'password','label' => 'Пароль Redis','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($values['redis_password']),'autocomplete' => 'new-password','description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($values['password_saved']
                                                ? 'Оставь пустым — сохранённый пароль не изменится.'
                                                : 'Если Redis без пароля — оставь пустым.')]); ?>
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
                            </div>
                        </fieldset>

                        <div class="md-setup__actions">
                            <?php if (isset($component)) { $__componentOriginale300843b4ce048894a92319c5215c17c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale300843b4ce048894a92319c5215c17c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.button','data' => ['href' => ''.e($base).'&step=2','variant' => 'ghost','icon' => 'arrow-left']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e($base).'&step=2','variant' => 'ghost','icon' => 'arrow-left']); ?>Назад <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $attributes = $__attributesOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__attributesOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $component = $__componentOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__componentOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
                            <?php if (isset($component)) { $__componentOriginale300843b4ce048894a92319c5215c17c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale300843b4ce048894a92319c5215c17c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.button','data' => ['type' => 'submit','name' => 'action','value' => 'check','variant' => 'secondary','icon' => 'plug-connected']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','name' => 'action','value' => 'check','variant' => 'secondary','icon' => 'plug-connected']); ?>
                                Проверить подключение
                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $attributes = $__attributesOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__attributesOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $component = $__componentOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__componentOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
                            <?php if (isset($component)) { $__componentOriginale300843b4ce048894a92319c5215c17c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale300843b4ce048894a92319c5215c17c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.button','data' => ['type' => 'submit','icon' => 'arrow-right','disabled' => $blocked]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','icon' => 'arrow-right','disabled' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($blocked)]); ?>Продолжить <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $attributes = $__attributesOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__attributesOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $component = $__componentOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__componentOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
                        </div>
                    </form>
                <?php elseif($step === 4): ?>
                    <header class="md-setup__head">
                        <span class="md-setup__eyebrow">Шаг 4 из 4</span>
                        <h1 class="md-setup__title">Создадим хозяина дома</h1>
                        <p class="md-setup__lead">
                            Этот аккаунт получит полный доступ. Логин и пароль понадобятся
                            для входа в панель — запиши их в надёжное место.
                        </p>
                    </header>

                    <?php if(isset($errors['global'])): ?>
                        <?php if (isset($component)) { $__componentOriginal67ec6f84581691906d10e0e28720d229 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal67ec6f84581691906d10e0e28720d229 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.alert','data' => ['type' => 'danger','title' => 'Установка не завершилась','text' => $errors['global']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'danger','title' => 'Установка не завершилась','text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors['global'])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $attributes = $__attributesOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__attributesOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $component = $__componentOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__componentOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
                    <?php elseif($errors !== []): ?>
                        <?php if (isset($component)) { $__componentOriginal67ec6f84581691906d10e0e28720d229 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal67ec6f84581691906d10e0e28720d229 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.alert','data' => ['type' => 'danger','title' => 'Проверь поля','text' => 'Пока есть ошибки в аккаунте, установка не начнётся.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'danger','title' => 'Проверь поля','text' => 'Пока есть ошибки в аккаунте, установка не начнётся.']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $attributes = $__attributesOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__attributesOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal67ec6f84581691906d10e0e28720d229)): ?>
<?php $component = $__componentOriginal67ec6f84581691906d10e0e28720d229; ?>
<?php unset($__componentOriginal67ec6f84581691906d10e0e28720d229); ?>
<?php endif; ?>
                    <?php endif; ?>

                    <div class="md-setup__summary md-setup__summary--compact">
                        <div class="md-setup__summary-item">
                            <span class="md-setup__summary-label">База</span>
                            <strong><?php echo e($summary['db'] !== '' ? $summary['db'] : '—'); ?></strong>
                        </div>
                        <div class="md-setup__summary-item">
                            <span class="md-setup__summary-label">Кэш</span>
                            <strong><?php echo e($summary['cache'] === 'redis' ? 'Redis' : 'Файлы'); ?></strong>
                        </div>
                    </div>

                    <form method="post" action="/install" class="md-setup__form">
                        <input type="hidden" name="_token" value="<?php echo e($csrf); ?>">
                        <input type="hidden" name="key" value="<?php echo e($token); ?>">
                        <input type="hidden" name="stage" value="install">

                        <div class="md-setup__grid">
                            <?php if (isset($component)) { $__componentOriginalb886b7d9c7f257ca97615d7374fcf93f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb886b7d9c7f257ca97615d7374fcf93f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.input','data' => ['id' => 'admin-login','name' => 'admin_login','label' => 'Логин','value' => $values['admin_login'],'error' => $errors['admin_login'] ?? null,'required' => true,'description' => '8–64 символа: латиница, цифры и _. Это имя для входа.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'admin-login','name' => 'admin_login','label' => 'Логин','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($values['admin_login']),'error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors['admin_login'] ?? null),'required' => true,'description' => '8–64 символа: латиница, цифры и _. Это имя для входа.']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.input','data' => ['id' => 'admin-name','name' => 'admin_name','label' => 'Имя','value' => $values['admin_name'],'error' => $errors['admin_name'] ?? null,'required' => true,'description' => 'Как показывать тебя в панели.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'admin-name','name' => 'admin_name','label' => 'Имя','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($values['admin_name']),'error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors['admin_name'] ?? null),'required' => true,'description' => 'Как показывать тебя в панели.']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.input','data' => ['id' => 'admin-password','name' => 'admin_password','type' => 'password','label' => 'Пароль','value' => '','error' => $errors['admin_password'] ?? null,'autocomplete' => 'new-password','required' => true,'description' => 'Минимум 8 символов и один спецсимвол: !, ?, #.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'admin-password','name' => 'admin_password','type' => 'password','label' => 'Пароль','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(''),'error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors['admin_password'] ?? null),'autocomplete' => 'new-password','required' => true,'description' => 'Минимум 8 символов и один спецсимвол: !, ?, #.']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.input','data' => ['id' => 'admin-password-confirm','name' => 'admin_password_confirm','type' => 'password','label' => 'Пароль ещё раз','value' => '','error' => $errors['admin_password_confirm'] ?? null,'autocomplete' => 'new-password','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'admin-password-confirm','name' => 'admin_password_confirm','type' => 'password','label' => 'Пароль ещё раз','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(''),'error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors['admin_password_confirm'] ?? null),'autocomplete' => 'new-password','required' => true]); ?>
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
                            <?php if (isset($component)) { $__componentOriginal50a282dab5e65725bdbf885f265e7fd4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal50a282dab5e65725bdbf885f265e7fd4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.select','data' => ['id' => 'timezone','name' => 'timezone','label' => 'Часовой пояс','options' => array_combine($timezones, $timezones),'selected' => $values['timezone'],'error' => $errors['timezone'] ?? null,'required' => true,'description' => 'Время событий и журналов.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'timezone','name' => 'timezone','label' => 'Часовой пояс','options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(array_combine($timezones, $timezones)),'selected' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($values['timezone']),'error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors['timezone'] ?? null),'required' => true,'description' => 'Время событий и журналов.']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal50a282dab5e65725bdbf885f265e7fd4)): ?>
<?php $attributes = $__attributesOriginal50a282dab5e65725bdbf885f265e7fd4; ?>
<?php unset($__attributesOriginal50a282dab5e65725bdbf885f265e7fd4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal50a282dab5e65725bdbf885f265e7fd4)): ?>
<?php $component = $__componentOriginal50a282dab5e65725bdbf885f265e7fd4; ?>
<?php unset($__componentOriginal50a282dab5e65725bdbf885f265e7fd4); ?>
<?php endif; ?>
                        </div>

                        <div class="md-setup__actions">
                            <?php if (isset($component)) { $__componentOriginale300843b4ce048894a92319c5215c17c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale300843b4ce048894a92319c5215c17c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.button','data' => ['href' => ''.e($base).'&step=3','variant' => 'ghost','icon' => 'arrow-left']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e($base).'&step=3','variant' => 'ghost','icon' => 'arrow-left']); ?>Назад <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $attributes = $__attributesOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__attributesOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $component = $__componentOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__componentOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
                            <?php if (isset($component)) { $__componentOriginale300843b4ce048894a92319c5215c17c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale300843b4ce048894a92319c5215c17c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.md.button','data' => ['type' => 'submit','icon' => 'rocket','size' => 'lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('md.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','icon' => 'rocket','size' => 'lg']); ?>Установить LaIOT <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $attributes = $__attributesOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__attributesOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale300843b4ce048894a92319c5215c17c)): ?>
<?php $component = $__componentOriginale300843b4ce048894a92319c5215c17c; ?>
<?php unset($__componentOriginale300843b4ce048894a92319c5215c17c); ?>
<?php endif; ?>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>

    <p class="md-setup__foot">LaIOT · LaIOT · установка</p>
</main>
<script src="/assets/admin/laiot.js" defer></script>
</body>
</html>
<?php /**PATH /var/www/html/resources/views/install/page.blade.php ENDPATH**/ ?>