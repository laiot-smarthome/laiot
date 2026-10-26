<!doctype html>
<html lang="ru" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} — MyDoMo</title>
    <link rel="stylesheet" href="/assets/admin/admin.css">
    <script>
        (function () {
            var match = document.cookie.match(/(?:^|;\s*)theme=([^;]+)/);
            var theme = match ? decodeURIComponent(match[1]) : 'light';
            document.documentElement.setAttribute('data-bs-theme', theme === 'dark' ? 'dark' : 'light');
        })();
    </script>
</head>
<body class="md-admin-shell">
<div id="partLoadContent" class="md-admin-page">
    <div class="row md-admin-layout" id="content">
        <section class="md-admin-topbar" aria-label="Верхняя панель">
            <div class="md-admin-topbar__left">
                <button type="button" class="md-admin-topbar__menu-toggle md-topbar-action" data-md-admin-sidebar-toggle aria-label="Открыть меню">
                    <i class="ti ti-list" aria-hidden="true"></i>
                </button>
                <div class="md-admin-topbar__search" data-md-header-search>
                    <i class="ti ti-search md-admin-topbar__search-icon" aria-hidden="true"></i>
                    <input type="search" id="mdHeaderSearchInput" class="md-admin-topbar__search-input"
                           placeholder="Поиск по панели" autocomplete="off" spellcheck="false"
                           aria-label="Поиск по панели" aria-controls="mdHeaderSearchResults" aria-expanded="false">
                    <kbd class="md-admin-topbar__search-kbd">Ctrl K</kbd>
                    <div class="md-admin-topbar__search-results" id="mdHeaderSearchResults" role="listbox" aria-label="Результаты поиска" hidden></div>
                </div>
            </div>
            <div class="md-admin-topbar__actions">
                <a class="md-topbar-action" href="/api/v1/health" aria-label="Health">
                    <i class="ti ti-heartbeat" aria-hidden="true"></i><span>Health</span>
                </a>
                <a class="md-topbar-action" href="/panel/xray.html" aria-label="X-Ray">
                    <i class="ti ti-activity" aria-hidden="true"></i><span>X-Ray</span>
                </a>
                <div class="md-topbar-theme" role="group" aria-label="Тема оформления">
                    <input class="btn-check" type="radio" name="topbarTheme" id="topbarThemeLight" value="light" data-md-theme-choice="light">
                    <label for="topbarThemeLight" title="Светлая тема"><i class="ti ti-sun" aria-hidden="true"></i><span class="visually-hidden">Светлая тема</span></label>
                    <input class="btn-check" type="radio" name="topbarTheme" id="topbarThemeDark" value="dark" data-md-theme-choice="dark">
                    <label for="topbarThemeDark" title="Тёмная тема"><i class="ti ti-moon-stars" aria-hidden="true"></i><span class="visually-hidden">Тёмная тема</span></label>
                </div>
                <form method="post" action="/logout">
                    <input type="hidden" name="_token" value="{{ $csrf }}">
                    <button type="submit" class="md-topbar-action" aria-label="Выйти">
                        <i class="ti ti-logout" aria-hidden="true"></i><span>Выйти</span>
                    </button>
                </form>
            </div>
        </section>

        <aside class="col-lg-2 col-md-3 col-sm-4 col-xs-12 md-admin-sidebar" id="left_block" aria-label="Admin navigation">
            <div class="md-admin-sidebar__inner">
                <div class="md-admin-sidebar__brand-shell">
                    <a class="md-admin-sidebar__brand" href="/" aria-label="MyDoMo">MyDoMo</a>
                </div>
                <div class="md-admin-sidebar__scroll">
                    <nav class="md-admin-sidebar__nav" aria-label="Admin modules">
                        @foreach ($menu ?? [] as $group)
                            <section class="md-admin-sidebar__group">
                                @if (!empty($group['label']))<div class="md-admin-sidebar__group-title">{{ $group['label'] }}</div>@endif
                                <ul class="md-admin-sidebar__list">
                                    @foreach ($group['items'] ?? [] as $item)
                                        <li @class(['md-admin-sidebar__item', 'active' => ($active ?? '') === ($item['id'] ?? '')])>
                                            <a class="md-admin-sidebar__link leftmenutab" href="{{ $item['href'] }}">
                                                <i class="md-admin-sidebar__icon-bi ti {{ $item['icon'] ?? 'ti-point' }}" aria-hidden="true"></i>
                                                <span class="md-admin-sidebar__label">{{ $item['label'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endforeach
                    </nav>
                </div>
                <div class="md-admin-sidebar__profile">
                    <div class="md-admin-sidebar__account">
                        <span class="md-admin-sidebar__avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr((string)($user['name'] ?? '?'), 0, 1)) }}</span>
                        <span class="md-admin-sidebar__account-text">
                            <span class="md-admin-sidebar__account-name">{{ $user['name'] ?? $user['username'] ?? 'Гость' }}</span>
                            <span class="md-admin-sidebar__account-role">{{ ($user['is_admin'] ?? false) ? 'Администратор' : 'Пользователь' }}</span>
                        </span>
                    </div>
                    <form method="post" action="/logout">
                        <input type="hidden" name="_token" value="{{ $csrf }}">
                        <button type="submit" class="dropdown-item md-admin-sidebar__profile-logout">
                            <i class="ti ti-logout" aria-hidden="true"></i><span>Выйти</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <main class="col-lg-10 col-md-9 col-sm-8 col-xs-12 md-admin-main" id="main-content">
            <div class="md-admin-contextbar">
                <ol class="breadcrumb md-admin-breadcrumb">
                    <li><a href="/">Панель</a></li>
                    <li class="active">{{ $title }}</li>
                </ol>
            </div>
            {!! $content !!}
        </main>
    </div>
</div>

<div class="md-admin-search-backdrop" data-md-search-close></div>
<aside class="md-admin-search-drawer" id="mdmGlobalSearchDrawer" aria-labelledby="mdmGlobalSearchTitle" aria-hidden="true">
    <div class="md-admin-search-drawer__header">
        <div>
            <div class="md-admin-search-drawer__eyebrow">Панель управления</div>
            <h2 id="mdmGlobalSearchTitle">Поиск</h2>
        </div>
        <button type="button" class="btn-close" data-md-search-close aria-label="Закрыть"></button>
    </div>
    <div class="md-admin-search-drawer__body">
        <div class="md-admin-search-field">
            <span class="md-admin-search-field__icon" aria-hidden="true"><i class="ti ti-search"></i></span>
            <input type="text" class="form-control" name="filter_modules" id="filter_modules"
                   placeholder="Объект, класс, свойство, метод, скрипт" autocomplete="off">
            <button type="button" class="md-admin-search-field__clear" id="btnFilterCloseSearch"
                    data-md-search-clear aria-label="Очистить">×</button>
        </div>
        <div class="md-admin-search-hint searchNoty">Введите минимум 3 символа. Результаты сгруппированы по разделам.</div>
        <div class="md-admin-search-summary" id="filter_summary"></div>
        <div id="filter_add" class="md-admin-search-results" aria-live="polite"></div>
    </div>
</aside>

<script src="/assets/admin/admin.js" defer></script>
@stack('scripts')
</body>
</html>
