{{--
    Автономная страница ошибки: без PanelRenderer, БД и меню, чтобы
    открываться при сбое приложения. Маскот и код ошибки — единственный
    визуальный акцент, ниже обязательные объяснение, адрес и действие.
--}}
@php
    // Версия ассета, как в layouts.alpha: после сборки браузер должен
    // получить новый CSS, а не старую копию из кэша.
    $assetVersion = static function (string $path): string {
        $root = \LaIOT\Support\AppContext::get()?->root();
        $file = $root !== null ? $root . '/public' . $path : '';
        return $file !== '' && is_file($file) ? '?v=' . filemtime($file) : '';
    };
@endphp
<!doctype html>
<html lang="ru" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $status }} — {{ $title }} — LaIOT</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="stylesheet" href="/assets/admin/laiot.css{{ $assetVersion('/assets/admin/laiot.css') }}">
    <script>
        (function () {
            var match = document.cookie.match(/(?:^|;\s*)theme=([^;]+)/);
            var theme = match ? match[1] : 'light';
            document.documentElement.setAttribute('data-bs-theme', theme === 'dark' ? 'dark' : 'light');
        })();
    </script>
</head>
<body class="md-admin-shell md-error-page" data-error-code="{{ $code }}" data-error-status="{{ $status }}">
<main class="md-error-page__main">
    <div @class(['md-error-page__card', 'md-error-page__card--' . $tone])>
        <a class="md-error-page__brand" href="{{ $home ?? '/login' }}" aria-label="LaIOT — в панель">
            <span class="md-logo">
                <img class="md-logo__light" src="/assets/img/logos/laiot-logo.png"
                     srcset="/assets/img/logos/laiot-logo@2x.png 2x"
                     width="333" height="101" alt="LaIOT" decoding="async">
                <img class="md-logo__dark" src="/assets/img/logos/laiot-logo-white.png"
                     srcset="/assets/img/logos/laiot-logo-white@2x.png 2x"
                     width="300" height="91" alt="LaIOT" decoding="async">
            </span>
        </a>

        <div class="md-error-page__scene">
            <x-md.mascot :pose="$mascot" />
            <span class="md-error-page__code">{{ $status }}</span>
        </div>

        <h1 class="md-error-page__title">{{ $title }}</h1>
        <p class="md-error-page__text">{{ $text }}</p>

        @if ($path !== '')
            <p class="md-error-page__path" title="{{ $path }}">{{ $path }}</p>
        @endif

        @if ($status >= 500)
            <p class="md-error-page__request">Код запроса: <code>{{ $requestId }}</code></p>
        @endif

        @if ($debug !== null)
            <details class="md-error-page__debug">
                <summary>Технические подробности</summary>
                <pre>{{ $debug }}</pre>
            </details>
        @endif

        <div class="md-error-page__actions">
            @if ($retry)
                <x-md.button type="button" icon="refresh" onclick="location.reload()">Повторить</x-md.button>
            @endif
            @if ($home !== null)
                <x-md.button href="{{ $home }}" :variant="$retry ? 'secondary' : 'primary'" icon="home">В панель</x-md.button>
            @endif
            @if ($back)
                <x-md.button type="button" variant="secondary" icon="arrow-left"
                             onclick="history.length > 1 ? history.back() : location.assign('/admin')">Назад</x-md.button>
            @endif
        </div>
    </div>
</main>
</body>
</html>
