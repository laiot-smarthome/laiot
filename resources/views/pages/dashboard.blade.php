<div class="md-dash">
    @php
        $formatTime = static function (?string $value): string {
            if ($value === null || $value === '' || strlen($value) < 16) {
                return '—';
            }

            return substr($value, 8, 2) . '.' . substr($value, 5, 2) . ' · ' . substr($value, 11, 5);
        };
        $healthy = $databaseOk && $cacheOk && !$maintenance;
        $queueTotal = (int)$queue['pending'] + (int)$queue['reserved'];
        $errorsToday = (int)$counts['errors24'];
        // Склонение счётчиков: «1 пользователь», «2 пользователя», «5 пользователей».
        $plural = static function (int $n, string $one, string $few, string $many): string {
            $mod100 = $n % 100;
            $mod10 = $n % 10;
            if ($mod100 >= 11 && $mod100 <= 19) {
                return $many;
            }
            if ($mod10 === 1) {
                return $one;
            }
            if ($mod10 >= 2 && $mod10 <= 4) {
                return $few;
            }

            return $many;
        };
    @endphp

    <header class="md-dash__header">
        <div class="md-dash__heading">
            <p class="md-dash__eyebrow"><span class="md-dash__live-dot" aria-hidden="true"></span>Обзор системы</p>
            <h1 class="md-dash__title">Панель управления</h1>
            <p class="md-dash__subtitle">{{ $now->format('d.m.Y') }} · сводка состояния умного дома</p>
        </div>
        <x-md.mascot pose="love_home" class="md-dash__mascot" />
        <div class="md-dash__actions">
            <div class="md-dash__status" data-state="{{ $healthy ? 'ok' : 'warning' }}" role="status">
                <span class="md-dash__status-dot" aria-hidden="true"></span>
                <span>{{ $healthy ? 'Система работает штатно' : 'Есть замечания — проверьте состояние' }}</span>
                @if (!$healthy)<a class="md-dash__status-link" href="/status">Подробнее</a>@endif
            </div>
            <span class="md-dash__updated">Обновлено <time datetime="{{ $now->toIso8601String() }}">{{ $now->format('H:i') }}</time></span>
            <a class="md-btn md-btn--secondary md-btn--sm" href="/status">
                <i class="ti ti-activity" aria-hidden="true"></i><span class="md-btn__label">Состояние</span>
            </a>
            <a class="md-btn md-btn--primary md-btn--sm" href="/objects/create">
                <i class="ti ti-plus" aria-hidden="true"></i><span class="md-btn__label">Объект</span>
            </a>
        </div>
    </header>

    @if ($maintenance)
        <div class="md-dash__alerts">
            <div class="md-alert md-alert--warning md-dash-alert" role="status">
                <span class="md-alert__icon"><i class="ti ti-tool" aria-hidden="true"></i></span>
                <div class="md-alert__body">
                    <div class="md-alert__title">Включён режим обслуживания</div>
                    <div class="md-alert__text">Сайт и API отвечают 503, админка доступна администраторам.</div>
                    <div class="md-alert__actions">
                        <a class="md-btn md-btn--secondary md-btn--sm" href="/admin/maintenance">Управление режимом</a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <section class="md-dash__stats" aria-label="Ключевые показатели">
        <article class="md-dash-stat">
            <div class="md-dash-stat__head">
                <span class="md-dash-stat__icon md-dash-stat__icon--{{ $healthy ? 'success' : 'warning' }}">
                    <i class="ti ti-shield-check" aria-hidden="true"></i>
                </span>
                <span class="md-dash-stat__label">Состояние</span>
            </div>
            <div class="md-dash-stat__value">{{ $healthy ? 'Норма' : 'Внимание' }}</div>
            <div class="md-dash-stat__meta">
                База {{ $databaseOk ? 'доступна' : 'недоступна' }} · кэш {{ $cacheOk ? 'работает' : 'недоступен' }}
            </div>
        </article>

        <article class="md-dash-stat">
            <div class="md-dash-stat__head">
                <span class="md-dash-stat__icon"><i class="ti ti-box-seam" aria-hidden="true"></i></span>
                <span class="md-dash-stat__label">Объекты</span>
            </div>
            <div class="md-dash-stat__value">{{ number_format($counts['objects'], 0, ',', ' ') }}</div>
            <div class="md-dash-stat__meta">
                {{ number_format($counts['classes'], 0, ',', ' ') }} классов · {{ number_format($counts['locations'], 0, ',', ' ') }} локаций
            </div>
        </article>

        <article class="md-dash-stat">
            <div class="md-dash-stat__head">
                <span class="md-dash-stat__icon"><i class="ti ti-bolt" aria-hidden="true"></i></span>
                <span class="md-dash-stat__label">Изменения за сутки</span>
            </div>
            <div class="md-dash-stat__value">{{ number_format($counts['events24'], 0, ',', ' ') }}</div>
            <div class="md-dash-stat__meta">
                {{ number_format($counts['properties'], 0, ',', ' ') }} свойств · {{ number_format($counts['methods'], 0, ',', ' ') }} методов
            </div>
        </article>

        <article class="md-dash-stat">
            <div class="md-dash-stat__head">
                <span class="md-dash-stat__icon"><i class="ti ti-file-code" aria-hidden="true"></i></span>
                <span class="md-dash-stat__label">Сценарии</span>
            </div>
            <div class="md-dash-stat__value">{{ number_format($counts['scripts'], 0, ',', ' ') }}</div>
            <div class="md-dash-stat__meta">
                {{ number_format($counts['users'], 0, ',', ' ') }} {{ $plural((int)$counts['users'], 'пользователь', 'пользователя', 'пользователей') }} · {{ number_format($counts['actions24'], 0, ',', ' ') }} действий за сутки
            </div>
        </article>

        <article class="md-dash-stat">
            <div class="md-dash-stat__head">
                <span class="md-dash-stat__icon {{ $errorsToday > 0 ? 'md-dash-stat__icon--danger' : 'md-dash-stat__icon--success' }}">
                    <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                </span>
                <span class="md-dash-stat__label">Ошибки за сутки</span>
            </div>
            <div class="md-dash-stat__value">{{ number_format($errorsToday, 0, ',', ' ') }}</div>
            <div class="md-dash-stat__meta">
                <a href="/errors">{{ $errorsToday > 0 ? 'Открыть журнал ошибок' : 'Журнал пуст' }}</a>
            </div>
        </article>
    </section>

    <div class="md-dash__grid">
        <div class="md-dash__col md-dash__col--main">
            <section class="md-dash-card" aria-labelledby="mdDashEventsTitle">
                <header class="md-dash-card__head">
                    <div>
                        <h2 class="md-dash-card__title" id="mdDashEventsTitle">Активность дома</h2>
                        <p class="md-dash-card__subtitle">Последние изменения свойств и обработчики</p>
                    </div>
                    <a class="md-dash-card__link" href="/events">
                        Все события <i class="ti ti-arrow-up-right" aria-hidden="true"></i>
                    </a>
                </header>
                <div class="md-dash-card__body">
                    @if ($events === [])
                        <div class="md-dash-empty">
                            <i class="ti ti-history" aria-hidden="true"></i>
                            <span>Изменений свойств пока не было. Они появятся здесь после записи через сценарий или панель.</span>
                        </div>
                    @else
                        <div class="md-dash-activity">
                            @foreach ($events as $event)
                                <div class="md-dash-activity__item">
                                    <span class="md-dash-activity__dot" aria-hidden="true"></span>
                                    <div class="md-dash-activity__main">
                                        <span class="md-dash-activity__name">
                                            {{ $event['object'] !== '' ? $event['object'] . '.' . $event['property'] : ($event['property'] ?: 'Событие #' . $event['id']) }}
                                        </span>
                                        <span class="md-dash-activity__meta">
                                            {{ $formatTime($event['at']) }}
                                            @if ($event['old'] !== '') · было {{ $event['old'] }} @endif
                                        </span>
                                    </div>
                                    <span class="md-dash-activity__value">
                                        <strong>{{ $event['value'] !== '' ? $event['value'] : '—' }}</strong>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section class="md-dash-card" aria-labelledby="mdDashQueueTitle">
                <header class="md-dash-card__head">
                    <div>
                        <h2 class="md-dash-card__title" id="mdDashQueueTitle">Очередь заданий</h2>
                        <p class="md-dash-card__subtitle">Фоновые операции и повторы</p>
                    </div>
                    <a class="md-dash-card__link" href="/jobs">
                        Открыть очередь <i class="ti ti-arrow-up-right" aria-hidden="true"></i>
                    </a>
                </header>
                <div class="md-dash-card__body">
                    <dl class="md-dash-metrics md-dash-metrics--three">
                        <div>
                            <dt>В ожидании</dt>
                            <dd>{{ number_format((int)$queue['pending'], 0, ',', ' ') }}</dd>
                        </div>
                        <div>
                            <dt>Выполняются</dt>
                            <dd>{{ number_format((int)$queue['reserved'], 0, ',', ' ') }}</dd>
                        </div>
                        <div>
                            <dt>С ошибкой</dt>
                            <dd>{{ number_format((int)$queue['failed'], 0, ',', ' ') }}</dd>
                        </div>
                    </dl>
                    <p class="md-dash-note">
                        @if ($queueTotal === 0 && (int)$queue['failed'] === 0)
                            Очередь пуста — фоновые задания не накопились.
                        @else
                            Всего в работе {{ number_format($queueTotal, 0, ',', ' ') }} заданий. Воркер запускается командой <code>php bin/laiot queue:work</code>.
                        @endif
                    </p>
                </div>
            </section>
        </div>

        <div class="md-dash__col md-dash__col--side">
            <section class="md-dash-card" aria-labelledby="mdDashErrorsTitle">
                <header class="md-dash-card__head">
                    <div>
                        <h2 class="md-dash-card__title" id="mdDashErrorsTitle">Требует внимания</h2>
                        <p class="md-dash-card__subtitle">Последние ошибки ядра</p>
                    </div>
                    <a class="md-dash-card__link" href="/errors">
                        Журнал <i class="ti ti-arrow-up-right" aria-hidden="true"></i>
                    </a>
                </header>
                <div class="md-dash-card__body">
                    @if ($errors === [])
                        <div class="md-dash-empty">
                            <i class="ti ti-circle-check" aria-hidden="true"></i>
                            <span>Ошибок нет — журнал пуст.</span>
                        </div>
                    @else
                        <ul class="md-dash-error-list">
                            @foreach ($errors as $error)
                                <li class="md-dash-error">
                                    <span class="md-dash-error__dot" aria-hidden="true"></span>
                                    <div class="md-dash-error__body">
                                        <span class="md-dash-error__title">{{ $error['message'] }}</span>
                                        <span class="md-dash-error__meta">{{ $formatTime($error['at']) }} · {{ $error['level'] }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            <section class="md-dash-card" aria-labelledby="mdDashActionsTitle">
                <header class="md-dash-card__head">
                    <div>
                        <h2 class="md-dash-card__title" id="mdDashActionsTitle">Действия администраторов</h2>
                        <p class="md-dash-card__subtitle">Вход, правка и удаление записей</p>
                    </div>
                    <a class="md-dash-card__link" href="/journal">
                        Журнал <i class="ti ti-arrow-up-right" aria-hidden="true"></i>
                    </a>
                </header>
                <div class="md-dash-card__body">
                    @if ($actions === [])
                        <div class="md-dash-empty">
                            <i class="ti ti-list-details" aria-hidden="true"></i>
                            <span>Записей пока нет.</span>
                        </div>
                    @else
                        <ul class="md-dash-error-list">
                            @foreach ($actions as $action)
                                <li class="md-dash-error">
                                    <span class="md-dash-error__dot" aria-hidden="true"></span>
                                    <div class="md-dash-error__body">
                                        <span class="md-dash-error__title">{{ $action['title'] }}</span>
                                        <span class="md-dash-error__meta">{{ $formatTime($action['at']) }}{{ $action['user'] !== '' ? ' · ' . $action['user'] : '' }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            <section class="md-dash-card" aria-labelledby="mdDashShortcutsTitle">
                <header class="md-dash-card__head">
                    <div>
                        <h2 class="md-dash-card__title" id="mdDashShortcutsTitle">Быстрый доступ</h2>
                    </div>
                </header>
                <div class="md-dash-card__body">
                    <div class="md-dash-shortcuts">
                        <a class="md-dash-shortcut" href="/classes">
                            <span class="md-dash-shortcut__icon"><i class="ti ti-hierarchy" aria-hidden="true"></i></span>
                            <span class="md-dash-shortcut__text">Классы и объекты</span>
                        </a>
                        <a class="md-dash-shortcut" href="/scripts">
                            <span class="md-dash-shortcut__icon"><i class="ti ti-file-code" aria-hidden="true"></i></span>
                            <span class="md-dash-shortcut__text">Скрипты</span>
                        </a>
                        <a class="md-dash-shortcut" href="/users">
                            <span class="md-dash-shortcut__icon"><i class="ti ti-users" aria-hidden="true"></i></span>
                            <span class="md-dash-shortcut__text">Пользователи</span>
                        </a>
                        <a class="md-dash-shortcut" href="/admin/modules">
                            <span class="md-dash-shortcut__icon"><i class="ti ti-puzzle" aria-hidden="true"></i></span>
                            <span class="md-dash-shortcut__text">Модули</span>
                        </a>
                        <a class="md-dash-shortcut" href="/settings">
                            <span class="md-dash-shortcut__icon"><i class="ti ti-settings" aria-hidden="true"></i></span>
                            <span class="md-dash-shortcut__text">Настройки</span>
                        </a>
                    </div>
                </div>
            </section>

            <section class="md-dash-card" aria-labelledby="mdDashSystemTitle">
                <header class="md-dash-card__head">
                    <div>
                        <h2 class="md-dash-card__title" id="mdDashSystemTitle">Система</h2>
                    </div>
                </header>
                <div class="md-dash-card__body">
                    <dl class="md-dash-kv">
                        <div><dt>Версия</dt><dd>{{ $version }}</dd></div>
                        <div><dt>PHP</dt><dd>{{ $php }}</dd></div>
                        <div><dt>Часовой пояс</dt><dd>{{ $timezone }}</dd></div>
                        <div>
                            <dt>Режим обслуживания</dt>
                            <dd>{{ $maintenance ? 'включён' : 'выключен' }}</dd>
                        </div>
                    </dl>
                    <p class="md-dash-note">
                        <a href="/admin/api">Документация API</a> · <a href="/admin/tokens">Токены</a> · <a href="/ui-kit">UI Kit</a>
                    </p>
                </div>
            </section>
        </div>
    </div>
</div>
