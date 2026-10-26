<!doctype html>
<html lang="ru" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Вход — MyDoMo</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="stylesheet" href="/assets/admin/admin.css">
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
<main class="md-auth__main">
    <div class="md-auth__brand">
        <span class="md-logo">
            <img class="md-logo__light" src="/assets/img/logos/logo-black-clean-small.png"
                 srcset="/assets/img/logos/logo-black-clean-large.png 2x"
                 width="300" height="79" alt="MyDoMo" decoding="async">
            <img class="md-logo__dark" src="/assets/img/logos/logo-white-clean-small.png"
                 srcset="/assets/img/logos/logo-white-clean-large.png 2x"
                 width="300" height="79" alt="MyDoMo" decoding="async">
        </span>
    </div>
    <h1 class="md-auth__title">Вход в панель управления</h1>
    <p class="md-auth__subtitle">Войдите с учётной записью администратора.</p>

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
</main>
<script src="/assets/admin/admin.js" defer></script>
</body>
</html>
