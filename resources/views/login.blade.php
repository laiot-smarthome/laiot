<!doctype html>
<html lang="ru" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Вход — LaIoT</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="stylesheet" href="/assets/admin/mydomo.css">
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
                 width="333" height="101" alt="LaIoT" decoding="async">
            <img class="md-logo__dark" src="/assets/img/logos/laiot-logo-white.png"
                 srcset="/assets/img/logos/laiot-logo-white@2x.png 2x"
                 width="300" height="91" alt="LaIoT" decoding="async">
        </span>
    </div>

    <div class="md-auth__panel">
        <h1 class="md-auth__title">Вход в панель управления</h1>
        <p class="md-auth__subtitle">Дом, автоматизации и устройства — в одном окне.</p>

        <x-md.card>
            <form method="post" action="/login" class="md-ui-form-stack">
                <input type="hidden" name="_token" value="{{ $csrf }}">
                @if ($error)
                    <x-md.hint tone="danger" title="Не удалось войти" :text="$error" />
                @endif
                <x-md.input id="username" name="username" label="Логин" :value="$username ?? ''"
                            autocomplete="username" required size="lg" />
                <x-md.input id="password" type="password" name="password" label="Пароль"
                            autocomplete="current-password" required size="lg" />
                <x-md.button type="submit" icon="login" size="lg" class="md-auth__submit" block>Войти</x-md.button>
            </form>
        </x-md.card>

        <p class="md-auth__foot"><i class="ti ti-shield-lock" aria-hidden="true"></i> Доступ только для администраторов системы.</p>
    </div>
</main>
<script src="/assets/admin/mydomo.js" defer></script>
</body>
</html>
