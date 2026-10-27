<!doctype html>
<html lang="ru" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ $csrf }}">
    <title>{{ $title }} — MyDoMo</title>
    <link rel="stylesheet" href="/assets/admin/mydomo.css">
    @yoyo_scripts
    <script>
        (function () {
            var match = document.cookie.match(/(?:^|;\s*)theme=([^;]+)/);
            var theme = match ? decodeURIComponent(match[1]) : 'light';
            document.documentElement.setAttribute('data-bs-theme', theme === 'dark' ? 'dark' : 'light');
        })();
    </script>
</head>
<body class="md-admin-shell">
<a class="md-shell-skip" href="#main-content">Перейти к содержимому</a>
<div id="partLoadContent" class="md-admin-page">
    <div class="row md-admin-layout" id="content">
        <header class="md-admin-topbar" aria-label="Верхняя панель">
            <div class="md-admin-topbar__left">
                <button type="button" class="md-admin-topbar__menu-toggle md-topbar-action" data-md-admin-sidebar-toggle aria-label="Открыть меню" aria-controls="left_block" aria-expanded="false">
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
                <a class="md-topbar-action" href="/status" aria-label="Состояние системы">
                    <i class="ti ti-heartbeat" aria-hidden="true"></i><span>Состояние</span>
                </a>
                <button type="button" class="md-topbar-action md-notifications-bell" data-md-notifications-open
                        aria-label="Уведомления" title="Уведомления">
                    <i class="ti ti-bell" aria-hidden="true"></i>
                    <span class="md-badge md-badge--danger md-notifications-bell__count"
                          data-md-notifications-count hidden aria-hidden="true">0</span>
                </button>
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
        </header>

        <aside class="col-lg-2 col-md-3 col-sm-4 col-xs-12 md-admin-sidebar" id="left_block" aria-label="Боковая панель">
            <div class="md-admin-sidebar__inner">
                <div class="md-admin-sidebar__brand-shell">
                    <a class="md-admin-sidebar__brand" href="/admin" aria-label="MyDoMo — обзор"><i class="ti ti-smart-home" aria-hidden="true"></i><span>MyDoMo</span></a>
                </div>
                <div class="md-admin-sidebar__scroll">
                    <nav class="md-admin-sidebar__nav" aria-label="Разделы панели">
                        @foreach ($menu ?? [] as $group)
                            <section class="md-admin-sidebar__group">
                                @if (!empty($group['label']))<div class="md-admin-sidebar__group-title">{{ $group['label'] }}</div>@endif
                                <ul class="md-admin-sidebar__list">
                                    @foreach ($group['items'] ?? [] as $item)
                                        <li @class(['md-admin-sidebar__item', 'active' => ($active ?? '') === ($item['id'] ?? '')])>
                                            <a class="md-admin-sidebar__link leftmenutab" href="{{ $item['href'] }}" @if (($active ?? '') === ($item['id'] ?? '')) aria-current="page" @endif>
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
                    <a class="md-admin-sidebar__account" href="/admin/profile" title="Профиль и смена пароля">
                        <span class="md-admin-sidebar__avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr((string)($user['name'] ?? '?'), 0, 1)) }}</span>
                        <span class="md-admin-sidebar__account-text">
                            <span class="md-admin-sidebar__account-name">{{ $user['name'] ?? $user['username'] ?? 'Гость' }}</span>
                            <span class="md-admin-sidebar__account-role">{{ ($user['is_admin'] ?? false) ? 'Администратор' : 'Пользователь' }}</span>
                        </span>
                    </a>
                    <form method="post" action="/logout" class="md-inline-form">
                        <input type="hidden" name="_token" value="{{ $csrf }}">
                        <button type="submit" class="md-admin-sidebar__logout" aria-label="Выйти" title="Выйти">
                            <i class="ti ti-logout" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="md-admin-sidebar-backdrop" data-md-sidebar-close></div>

        <main class="col-lg-10 col-md-9 col-sm-8 col-xs-12 md-admin-main" id="main-content" tabindex="-1">
            <div class="md-admin-contextbar">
                <ol class="breadcrumb md-admin-breadcrumb">
                    <li><a href="/admin">Панель</a></li>
                    <li class="active" aria-current="page">{{ $title }}</li>
                </ol>
            </div>
            {{-- Содержимое раздела или SDK-модуля: общие header и меню уже созданы оболочкой. --}}
            {!! $content !!}
        </main>
    </div>
</div>

<div class="md-admin-drawer-backdrop" data-md-drawer-close hidden></div>

<div id="mdNotificationsContent" hidden>
    <div class="md-notifications-toolbar">
        <p class="text-body-secondary mb-0" data-md-notifications-status role="status">Загружаем уведомления…</p>
        <button type="button" class="md-btn md-btn--secondary md-btn--sm" data-md-notifications-refresh>
            <i class="ti ti-refresh" aria-hidden="true"></i><span class="md-btn__label">Обновить</span>
        </button>
    </div>
    <div class="md-notifications-list" data-md-notifications-list></div>
    <div class="md-notifications-toolbar" data-md-notifications-pages hidden>
        <button type="button" class="md-btn md-btn--secondary md-btn--sm" data-md-notifications-page="-1">Назад</button>
        <span class="text-body-secondary small" data-md-notifications-range></span>
        <button type="button" class="md-btn md-btn--secondary md-btn--sm" data-md-notifications-page="1">Далее</button>
    </div>
</div>

<aside class="md-admin-drawer md-drawer" id="mdAdminDrawerHost" aria-labelledby="mdAdminDrawerTitle"
       aria-hidden="true" tabindex="-1" hidden>
    <div class="md-admin-drawer__header md-drawer__header">
        <div>
            <div class="md-admin-drawer__eyebrow md-drawer__eyebrow" id="mdAdminDrawerEyebrow" hidden></div>
            <h2 class="md-drawer__title" id="mdAdminDrawerTitle"></h2>
            <div class="md-admin-drawer__subtitle md-drawer__subtitle" id="mdAdminDrawerSubtitle" hidden></div>
        </div>
        <button type="button" class="md-icon-btn md-admin-drawer__close" data-md-drawer-close aria-label="Закрыть">
            <i class="ti ti-x" aria-hidden="true"></i>
        </button>
    </div>
    <div class="md-admin-drawer__body md-drawer__body" id="mdAdminDrawerBody"></div>
    <div class="md-admin-drawer__footer md-drawer__footer" id="mdAdminDrawerFooter" hidden></div>
</aside>

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

<x-md.modal id="mdConfirmDialog" title="Подтвердите действие" size="sm">
    <p class="mb-0" x-text="$store.mdConfirm.message"></p>
    <x-slot:actions>
        <button type="button" class="md-btn md-btn--secondary" @click="$store.mdConfirm.cancel()">Отмена</button>
        <button type="button" class="md-btn md-btn--danger" @click="$store.mdConfirm.ok()">Подтвердить</button>
    </x-slot:actions>
</x-md.modal>

<x-md.toasts />
<script src="/assets/admin/mydomo.js" defer></script>
@stack('scripts')
</body>
</html>
