<!doctype html>
<html lang="ru" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ $csrf }}">
    <title>{{ $title }} — LaIoT</title>
    @php
        // После npm run build браузер должен получить новый файл, а не старый
        // из кэша: версия — время изменения собранного ассета.
        $assetVersion = static function (string $path): string {
            $root = \MajorDoMo\Support\AppContext::get()?->root();
            $file = $root !== null ? $root . '/public' . $path : '';
            return $file !== '' && is_file($file) ? '?v=' . filemtime($file) : '';
        };
    @endphp
    <link rel="stylesheet" href="/assets/admin/mydomo.css{{ $assetVersion('/assets/admin/mydomo.css') }}">
    @yoyo_scripts
    <script>
        (function () {
            var match = document.cookie.match(/(?:^|;\s*)theme=([^;]+)/);
            var theme = match ? match[1] : 'light';
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
                    <i class="ti ti-list md-sidebar-toggle-glyph md-sidebar-toggle-glyph--open" aria-hidden="true"></i>
                    <i class="ti ti-layout-sidebar-left-collapse md-sidebar-toggle-glyph md-sidebar-toggle-glyph--collapse" aria-hidden="true"></i>
                    <i class="ti ti-layout-sidebar-left-expand md-sidebar-toggle-glyph md-sidebar-toggle-glyph--expand" aria-hidden="true"></i>
                </button>
                <button type="button" class="md-topbar-action md-admin-topbar__mobile-search" data-md-search-open aria-label="Поиск по панели" aria-controls="mdmGlobalSearchDrawer">
                    <i class="ti ti-search" aria-hidden="true"></i>
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

        <aside class="md-admin-sidebar" id="left_block" aria-label="Боковая панель">
            <div class="md-admin-sidebar__inner">
                <div class="md-admin-sidebar__brand-shell">
                    <a class="md-admin-sidebar__brand" href="/admin" aria-label="LaIoT — обзор">
                        <img class="md-admin-sidebar__logo" src="/assets/img/logos/laiot-logo-white.png"
                             srcset="/assets/img/logos/laiot-logo-white@2x.png 2x"
                             width="114" height="35" alt="LaIoT" decoding="async">
                        <img class="md-admin-sidebar__mark" src="/assets/img/logos/laiot-mark.png"
                             srcset="/assets/img/logos/laiot-mark@2x.png 2x"
                             width="34" height="34" alt="" aria-hidden="true" decoding="async">
                    </a>
                    <button type="button" class="md-admin-sidebar__close" data-md-sidebar-close aria-label="Закрыть меню">
                        <i class="ti ti-x" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="md-admin-sidebar__scroll">
                    {{-- Группы и каталоги — нативные details/summary: аккордеон
                         работает без JavaScript, а клик по ссылке не сворачивает
                         раздел. Состояние запоминает sidebar.js. --}}
                    <nav class="md-nav" aria-label="Разделы панели">
                        @foreach ($menu ?? [] as $group)
                            @php($groupKey = $group['key'] ?? ('group-' . $loop->index))
                            @php($groupActive = ($active_group ?? null) === $groupKey)
                            @php($groupBadge = $group['badge'] ?? null)
                            <details class="md-nav__group @if ($groupActive) is-active @endif"
                                     data-md-nav-key="{{ $groupKey }}" data-md-nav-scope
                                     @if ($groupActive) open @endif>
                                <summary class="md-nav__group-toggle">
                                    <span class="md-nav__group-label">{{ $group['label'] ?? '' }}</span>
                                    <span @class(['md-nav__badge', 'md-nav__badge--group', 'md-nav__badge--' . ($groupBadge['tone'] ?? 'info')])
                                          data-md-attention-group data-md-count="{{ $groupBadge['count'] ?? 0 }}"
                                          data-md-tone="{{ $groupBadge['tone'] ?? 'info' }}"
                                          title="{{ $groupBadge['title'] ?? '' }}"
                                          @if (empty($groupBadge['count'])) hidden @endif>{{ $groupBadge['label'] ?? '0' }}</span>
                                    <i class="ti ti-chevron-down md-nav__chevron" aria-hidden="true"></i>
                                </summary>
                                <div class="md-nav__panel">
                                    <ul class="md-nav__list">
                                        @foreach ($group['items'] ?? [] as $item)
                                            @if (!empty($item['items']))
                                                @php($catalogKey = $item['key'] ?? ('catalog-' . $loop->index))
                                                @php($catalogActive = ($active_catalog ?? null) === $catalogKey)
                                                @php($catalogBadge = $item['badge'] ?? null)
                                                <li class="md-nav__catalog @if ($catalogActive) is-active @endif">
                                                    <details class="md-nav__sub" data-md-nav-key="{{ $catalogKey }}" data-md-nav-scope
                                                             @if ($catalogActive) open @endif>
                                                        <summary class="md-nav__catalog-toggle">
                                                            <i class="ti {{ $item['icon'] ?? 'ti-folder' }} md-nav__catalog-icon" aria-hidden="true"></i>
                                                            <span class="md-nav__label">{{ $item['label'] ?? '' }}</span>
                                                            <span @class(['md-nav__badge', 'md-nav__badge--group', 'md-nav__badge--' . ($catalogBadge['tone'] ?? 'info')])
                                                                  data-md-attention-group data-md-count="{{ $catalogBadge['count'] ?? 0 }}"
                                                                  data-md-tone="{{ $catalogBadge['tone'] ?? 'info' }}"
                                                                  title="{{ $catalogBadge['title'] ?? '' }}"
                                                                  @if (empty($catalogBadge['count'])) hidden @endif>{{ $catalogBadge['label'] ?? '0' }}</span>
                                                            <i class="ti ti-chevron-down md-nav__chevron" aria-hidden="true"></i>
                                                        </summary>
                                                        <div class="md-nav__panel md-nav__panel--sub">
                                                            <ul class="md-nav__list md-nav__list--sub">
                                                                @foreach ($item['items'] as $child)
                                                                    @include('layouts.partials.sidebar-item', ['item' => $child, 'active' => $active ?? ''])
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    </details>
                                                </li>
                                            @else
                                                @include('layouts.partials.sidebar-item', ['item' => $item, 'active' => $active ?? ''])
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                            </details>
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
                    <form method="post" action="/logout" class="md-admin-sidebar__logout-form">
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
            @foreach ($notifications ?? [] as $notification)
                <x-md.notify :type="$notification['type']" :title="$notification['title']" :text="$notification['text']" />
            @endforeach
            {{-- Содержимое раздела или SDK-модуля: общие header и меню уже созданы оболочкой. --}}
            {!! $content !!}
        </main>
    </div>
</div>

<div class="md-admin-drawer-backdrop" data-md-drawer-close hidden></div>

<div id="mdNotificationsContent" hidden>
    <p class="text-body-secondary mb-0" data-md-notifications-status role="status" hidden></p>
    <template data-md-notifications-loading>
        <x-md.loader block size="lg" label="Загрузка уведомлений" class="md-notifications-loading" />
    </template>
    <template data-md-notifications-empty>
        <x-md.mascot-empty />
    </template>
    <div class="md-notifications-list" data-md-notifications-list></div>
    <div class="md-notifications-toolbar" data-md-notifications-pages hidden>
        <button type="button" class="md-btn md-btn--secondary md-btn--sm" data-md-notifications-page="-1">Назад</button>
        <span class="text-body-secondary small" data-md-notifications-range></span>
        <button type="button" class="md-btn md-btn--secondary md-btn--sm" data-md-notifications-page="1">Далее</button>
    </div>
</div>

<aside class="md-admin-drawer md-drawer" id="mdAdminDrawerHost" aria-labelledby="mdAdminDrawerTitle"
       role="dialog" aria-modal="true" aria-hidden="true" tabindex="-1" hidden>
    <div class="md-admin-drawer__header md-drawer__header">
        <div>
            <div class="md-admin-drawer__eyebrow md-drawer__eyebrow" id="mdAdminDrawerEyebrow" hidden></div>
            <h2 class="md-drawer__title" id="mdAdminDrawerTitle"></h2>
            <div class="md-admin-drawer__subtitle md-drawer__subtitle" id="mdAdminDrawerSubtitle" hidden></div>
        </div>
        <div class="md-drawer__header-actions">
            <button type="button" class="md-icon-btn" data-md-notifications-refresh hidden aria-label="Обновить уведомления" title="Обновить уведомления">
                <i class="ti ti-refresh" aria-hidden="true"></i>
            </button>
            <button type="button" class="md-icon-btn md-admin-drawer__close" data-md-drawer-close aria-label="Закрыть">
                <i class="ti ti-x" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <div class="md-admin-drawer__body md-drawer__body" id="mdAdminDrawerBody"></div>
    <div class="md-admin-drawer__footer md-drawer__footer" id="mdAdminDrawerFooter" hidden></div>
</aside>

<div class="md-admin-search-backdrop" data-md-search-close></div>
<aside class="md-admin-search-drawer" id="mdmGlobalSearchDrawer" aria-labelledby="mdmGlobalSearchTitle" role="dialog" aria-modal="true" tabindex="-1" aria-hidden="true" inert>
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
                   aria-label="Поиск по панели"
                   placeholder="Объект, класс, свойство, метод, скрипт" autocomplete="off">
            <button type="button" class="md-admin-search-field__clear" id="btnFilterCloseSearch"
                    data-md-search-clear aria-label="Очистить">×</button>
        </div>
        <div class="md-admin-search-hint searchNoty">Введите минимум 3 символа. Результаты сгруппированы по разделам.</div>
        <div class="md-admin-search-summary" id="filter_summary"></div>
        <div id="filter_add" class="md-admin-search-results" aria-live="polite"></div>
    </div>
</aside>

<x-md.modal id="mdConfirmDialog" title="Подтвердите действие" size="md" class="md-modal--confirm">
    <p class="mb-0" x-text="$store.mdConfirm.message"></p>
    <x-slot:actions>
        <button type="button" class="md-btn md-btn--secondary" @click="$store.mdConfirm.cancel()">Отмена</button>
        <button type="button" class="md-btn md-btn--danger" @click="$store.mdConfirm.ok()">Подтвердить</button>
    </x-slot:actions>
</x-md.modal>

<x-md.toasts />
<div class="md-notify-attention" data-md-notify-attention aria-hidden="true"></div>
<script src="/assets/admin/mydomo.js{{ $assetVersion('/assets/admin/mydomo.js') }}" defer></script>
@stack('scripts')
</body>
</html>
