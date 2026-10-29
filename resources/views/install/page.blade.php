{{--
    Пошаговая установка LaIOT.
    Шаги серверные: каждый проверяет свои поля и не пускает дальше с ошибкой.
    2026-10-28 — мастер с приветствием, подсказками и анимациями вместо
    одной технической формы.
--}}
@php
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
@endphp
<!doctype html>
<html lang="ru" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Установка — LaIOT</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="stylesheet" href="/assets/admin/laiot.css{{ $assetVersion('/assets/admin/laiot.css') }}">
    <link rel="stylesheet" href="/assets/install/setup.css{{ $assetVersion('/assets/install/setup.css') }}">
    <script>
        (function () {
            var match = document.cookie.match(/(?:^|;\s*)theme=([^;]+)/);
            var theme = match ? match[1] : 'light';
            document.documentElement.setAttribute('data-bs-theme', theme === 'dark' ? 'dark' : 'light');
        })();
    </script>
</head>
<body class="md-admin-shell md-setup">
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
    <div class="md-setup__theme" role="group" aria-label="Тема оформления">
        <input class="btn-check" type="radio" name="installTheme" id="installThemeLight" value="light" data-md-theme-choice="light">
        <label for="installThemeLight" title="Светлая тема"><i class="ti ti-sun" aria-hidden="true"></i><span class="visually-hidden">Светлая тема</span></label>
        <input class="btn-check" type="radio" name="installTheme" id="installThemeDark" value="dark" data-md-theme-choice="dark">
        <label for="installThemeDark" title="Тёмная тема"><i class="ti ti-moon-stars" aria-hidden="true"></i><span class="visually-hidden">Тёмная тема</span></label>
    </div>
</header>

<main class="md-setup__main">

    @if ($state === 'unavailable')
        <section class="md-setup__panel md-setup__panel--center">
            <x-md.mascot pose="nothing" class="md-setup__mascot md-setup__mascot--small" />
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
            <x-md.button type="button" icon="refresh" onclick="location.reload()">Проверить снова</x-md.button>
        </section>
    @elseif ($state === 'key')
        <section class="md-setup__panel md-setup__panel--center">
            <x-md.mascot pose="quest" class="md-setup__mascot md-setup__mascot--small" />
            <h1 class="md-setup__title">Остался ключ</h1>
            <p class="md-setup__lead">
                Установщик заперт одноразовым ключом — так его не откроет кто-то чужой.
                Ключ уже лежит в файле <code>var/install.key</code>: открой его в файловом
                менеджере FastPanel или прочитай по SSH.
            </p>
            @if ($keyError !== null)
                <x-md.alert type="danger" title="Ключ не подошёл" :text="$keyError" />
            @endif
            <form method="get" action="/install" class="md-setup__key-form">
                <x-md.input id="install-key" name="key" label="Ключ из файла" autocomplete="off"
                            required size="lg" hint="64 символа: буквы и цифры" />
                <x-md.button type="submit" icon="arrow-right" size="lg">Открыть установщик</x-md.button>
            </form>
            <p class="md-setup__micro">После установки ключ удалится — страница закроется сама.</p>
        </section>
    @elseif ($state === 'done')
        <section class="md-setup__panel md-setup__panel--done">
            <div class="md-setup__done-hero">
                <x-md.mascot pose="success" class="md-setup__mascot" />
                <span class="md-setup__spark" style="--x: 8%; --d: .1s"></span>
                <span class="md-setup__spark" style="--x: 22%; --d: .35s"></span>
                <span class="md-setup__spark" style="--x: 74%; --d: .2s"></span>
                <span class="md-setup__spark" style="--x: 88%; --d: .5s"></span>
            </div>
            <h1 class="md-setup__title">Дом готов!</h1>
            <p class="md-setup__lead">
                LaIOT {{ $report->version }} установлена. Теперь можно войти и добавить
                первый этаж, комнату и устройства.
            </p>

            @foreach ($report->warnings as $warning)
                <x-md.alert type="warning" :text="$warning" />
            @endforeach

            <div class="md-setup__summary">
                <div class="md-setup__summary-item">
                    <span class="md-setup__summary-label">Хозяин дома</span>
                    <strong>{{ $report->adminLogin }}</strong>
                </div>
                <div class="md-setup__summary-item">
                    <span class="md-setup__summary-label">База и кэш</span>
                    <strong>{{ $report->cacheDriver === 'redis' ? 'Redis' : 'Файлы' }}</strong>
                </div>
                <div class="md-setup__summary-item">
                    <span class="md-setup__summary-label">Миграций</span>
                    <strong>{{ $report->migrationsCount() }}</strong>
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
                <x-md.button href="{{ $login }}" icon="login" size="lg">Войти в панель</x-md.button>
            </div>
        </section>
    @else
        <div class="md-setup__wizard">
            <ol class="md-setup__rail" aria-label="Шаги установки">
                @foreach ($stepTitles as $number => $title)
                    <li @class([
                        'md-setup__rail-item',
                        'is-done' => $step > $number,
                        'is-active' => $step === $number,
                    ])>
                        <span class="md-setup__rail-dot">
                            @if ($step > $number)
                                <x-md.icon name="check" />
                            @else
                                {{ $number }}
                            @endif
                        </span>
                        <span class="md-setup__rail-label">{{ $title }}</span>
                    </li>
                @endforeach
            </ol>

            <section class="md-setup__panel md-setup__panel--step-{{ $step }}"
                     data-step="{{ $step }}">
                @if ($step === 1)
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
                                <li><x-md.icon name="home" /> Свой дом</li>
                                <li><x-md.icon name="sparkles" /> Сценарии</li>
                                <li><x-md.icon name="shield-lock" /> Только локально</li>
                            </ul>
                            <form method="post" action="/install" class="md-setup__cta">
                                <input type="hidden" name="_token" value="{{ $csrf }}">
                                <input type="hidden" name="key" value="{{ $token }}">
                                <input type="hidden" name="stage" value="checks">
                                <x-md.button type="submit" icon="arrow-right" size="lg">Начать настройку</x-md.button>
                            </form>
                            <p class="md-setup__micro">Пара минут. Сначала всё проверим — ничего не сломаем.</p>
                        </div>
                        <div class="md-setup__hero-art">
                            <span class="md-setup__glow" aria-hidden="true"></span>
                            <x-md.mascot pose="hello" class="md-setup__mascot" />
                        </div>
                    </div>

                    <div class="md-setup__features">
                        <article class="md-setup__feature">
                            <span class="md-setup__feature-icon"><x-md.icon name="home" /></span>
                            <h2>Дом как есть</h2>
                            <p>Этажи, комнаты и объекты — понятная структура, а не свалка устройств.</p>
                        </article>
                        <article class="md-setup__feature">
                            <span class="md-setup__feature-icon"><x-md.icon name="sparkles" /></span>
                            <h2>Сценарии</h2>
                            <p>Свет по движению, климат по времени, шторы по солнцу.</p>
                        </article>
                        <article class="md-setup__feature">
                            <span class="md-setup__feature-icon"><x-md.icon name="plug-connected" /></span>
                            <h2>Устройства</h2>
                            <p>MQTT, Zigbee2MQTT, датчики и реле — в одном месте.</p>
                        </article>
                        <article class="md-setup__feature">
                            <span class="md-setup__feature-icon"><x-md.icon name="history" /></span>
                            <h2>История</h2>
                            <p>Графики и журнал: видно, что случилось и почему.</p>
                        </article>
                    </div>

                    <p class="md-setup__plan">
                        <strong>Дальше:</strong> проверим сервер → подключим базу → создадим хозяина дома.
                    </p>
                @elseif ($step === 2)
                    <header class="md-setup__head">
                        <span class="md-setup__eyebrow">Шаг 2 из 4</span>
                        <h1 class="md-setup__title">Проверим сервер</h1>
                        <p class="md-setup__lead">Смотрим, всё ли готово для LaIOT. Ничего не меняем — только читаем.</p>
                    </header>

                    @if ($blocked)
                        <x-md.alert type="danger" title="Пока нельзя продолжить"
                                    text="Исправь отмеченное и обнови страницу. Без этого установка не пойдёт." />
                    @else
                        <x-md.alert type="success" title="Всё готово"
                                    text="Сервер примет LaIOT. Можно продолжать." />
                    @endif

                    <ul class="md-setup__checks">
                        @foreach ($checks as $check)
                            <li @class(['md-setup__check', 'md-setup__check--' . $check['status']])
                                style="--i: {{ $loop->index }}">
                                <span class="md-setup__check-icon"><x-md.icon :name="$icon($check['status'])" /></span>
                                <span class="md-setup__check-body">
                                    <span class="md-setup__check-label">{{ $check['label'] }}</span>
                                    @if ($check['message'] !== '')
                                        <span class="md-setup__check-text">{{ $check['message'] }}</span>
                                    @endif
                                </span>
                                <span class="md-setup__check-state">{{ $checkTitle($check['status']) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="md-setup__actions">
                        <x-md.button href="{{ $base }}" variant="ghost" icon="arrow-left">Назад</x-md.button>
                        <x-md.link href="{{ $base }}&step=2" variant="action">Проверить снова</x-md.link>
                        <form method="post" action="/install" class="md-setup__inline">
                            <input type="hidden" name="_token" value="{{ $csrf }}">
                            <input type="hidden" name="key" value="{{ $token }}">
                            <input type="hidden" name="stage" value="connect">
                            <x-md.button type="submit" icon="arrow-right" :disabled="$blocked">Продолжить</x-md.button>
                        </form>
                    </div>
                @elseif ($step === 3)
                    <header class="md-setup__head">
                        <span class="md-setup__eyebrow">Шаг 3 из 4</span>
                        <h1 class="md-setup__title">Подключим базу</h1>
                        <p class="md-setup__lead">
                            Здесь будут жить дом, сценарии и история. Пароль сохранится
                            в <code>.env</code> на сервере и не вернётся в браузер.
                        </p>
                    </header>

                    @if (isset($errors['global']))
                        <x-md.alert type="danger" title="Не получилось продолжить" :text="$errors['global']" />
                    @elseif ($errors !== [])
                        <x-md.alert type="danger" title="Проверь поля"
                                    text="Пока есть ошибки в подключении, дальше не пойдём." />
                    @endif

                    @if ($preflight !== [])
                        <ul class="md-setup__checks md-setup__checks--compact">
                            @foreach ($preflight as $check)
                                <li @class(['md-setup__check', 'md-setup__check--' . $check['status']])
                                    style="--i: {{ $loop->index }}">
                                    <span class="md-setup__check-icon"><x-md.icon :name="$icon($check['status'])" /></span>
                                    <span class="md-setup__check-body">
                                        <span class="md-setup__check-label">{{ $check['label'] }}</span>
                                        @if ($check['message'] !== '')
                                            <span class="md-setup__check-text">{{ $check['message'] }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @foreach ($notices as $notice)
                        <x-md.alert type="success" :text="$notice" />
                    @endforeach

                    <form method="post" action="/install" class="md-setup__form">
                        <input type="hidden" name="_token" value="{{ $csrf }}">
                        <input type="hidden" name="key" value="{{ $token }}">
                        <input type="hidden" name="stage" value="connection">

                        <fieldset class="md-setup__group">
                            <legend>База данных</legend>
                            <p class="md-setup__group-lead">
                                MySQL или MariaDB. Если база уже создана в FastPanel — просто укажи её.
                            </p>
                            <div class="md-setup__grid">
                                <x-md.input id="db-host" name="db_host" label="Сервер" :value="$values['db_host']"
                                            :error="$errors['db_host'] ?? null" required
                                            description="Обычно 127.0.0.1 — там, где живёт база." />
                                <x-md.input id="db-port" name="db_port" type="number" label="Порт" :value="$values['db_port']"
                                            :error="$errors['db_port'] ?? null" required
                                            description="Стандартный — 3306." />
                                <x-md.input id="db-name" name="db_name" label="Имя базы" :value="$values['db_name']"
                                            :error="$errors['db_name'] ?? null" required
                                            description="Можно указать готовую или создать новую." />
                                <x-md.input id="db-user" name="db_user" label="Пользователь" :value="$values['db_user']"
                                            :error="$errors['db_user'] ?? null" required
                                            description="Отдельный пользователь LaIOT, не root." />
                                <x-md.input id="db-password" name="db_password" type="password" label="Пароль"
                                            :value="$values['db_password']" :error="$errors['db_password'] ?? null"
                                            autocomplete="new-password"
                                            :description="$values['password_saved']
                                                ? 'Оставь пустым — сохранённый пароль не изменится.'
                                                : 'Пароль из FastPanel. Хранится только в .env на сервере.'" />
                            </div>
                            <x-md.checkbox name="db_create" label="Создать базу, если её нет"
                                           description="Нужны права на создание. Если база уже есть в панели — сними галочку."
                                           :checked="$values['db_create']" />
                        </fieldset>

                        <fieldset class="md-setup__group">
                            <legend>Память и очередь</legend>
                            <p class="md-setup__group-lead">
                                Redis делает фоновые задачи быстрее и не даёт им дублироваться.
                                Файлы подойдут, если Redis пока нет.
                            </p>
                            <div class="md-setup__grid">
                                <x-md.select id="cache-driver" name="cache_driver" label="Где хранить кэш"
                                             :options="['redis' => 'Redis', 'file' => 'Файлы']"
                                             :selected="$values['cache_driver']" :error="$errors['cache_driver'] ?? null"
                                             description="Redis — для рабочего сервера, файлы — для одной машины." />
                            </div>
                            <div class="md-setup__grid">
                                <x-md.input id="redis-host" name="redis_host" label="Сервер Redis" :value="$values['redis_host']"
                                            :error="$errors['redis_host'] ?? null" description="Обычно 127.0.0.1." />
                                <x-md.input id="redis-port" name="redis_port" type="number" label="Порт Redis"
                                            :value="$values['redis_port']" :error="$errors['redis_port'] ?? null"
                                            description="Стандартный — 6379." />
                                <x-md.input id="redis-database" name="redis_database" type="number" label="База Redis"
                                            :value="$values['redis_database']" :error="$errors['redis_database'] ?? null"
                                            description="0–15. Лучше отдельная, не 0." />
                                <x-md.input id="redis-password" name="redis_password" type="password" label="Пароль Redis"
                                            :value="$values['redis_password']" autocomplete="new-password"
                                            :description="$values['password_saved']
                                                ? 'Оставь пустым — сохранённый пароль не изменится.'
                                                : 'Если Redis без пароля — оставь пустым.'" />
                            </div>
                        </fieldset>

                        <div class="md-setup__actions">
                            <x-md.button href="{{ $base }}&step=2" variant="ghost" icon="arrow-left">Назад</x-md.button>
                            <x-md.button type="submit" name="action" value="check" variant="secondary" icon="plug-connected">
                                Проверить подключение
                            </x-md.button>
                            <x-md.button type="submit" icon="arrow-right" :disabled="$blocked">Продолжить</x-md.button>
                        </div>
                    </form>
                @elseif ($step === 4)
                    <header class="md-setup__head">
                        <span class="md-setup__eyebrow">Шаг 4 из 4</span>
                        <h1 class="md-setup__title">Создадим хозяина дома</h1>
                        <p class="md-setup__lead">
                            Этот аккаунт получит полный доступ. Логин и пароль понадобятся
                            для входа в панель — запиши их в надёжное место.
                        </p>
                    </header>

                    @if (isset($errors['global']))
                        <x-md.alert type="danger" title="Установка не завершилась" :text="$errors['global']" />
                    @elseif ($errors !== [])
                        <x-md.alert type="danger" title="Проверь поля"
                                    text="Пока есть ошибки в аккаунте, установка не начнётся." />
                    @endif

                    <div class="md-setup__summary md-setup__summary--compact">
                        <div class="md-setup__summary-item">
                            <span class="md-setup__summary-label">База</span>
                            <strong>{{ $summary['db'] !== '' ? $summary['db'] : '—' }}</strong>
                        </div>
                        <div class="md-setup__summary-item">
                            <span class="md-setup__summary-label">Кэш</span>
                            <strong>{{ $summary['cache'] === 'redis' ? 'Redis' : 'Файлы' }}</strong>
                        </div>
                    </div>

                    <form method="post" action="/install" class="md-setup__form">
                        <input type="hidden" name="_token" value="{{ $csrf }}">
                        <input type="hidden" name="key" value="{{ $token }}">
                        <input type="hidden" name="stage" value="install">

                        <div class="md-setup__grid">
                            <x-md.input id="admin-login" name="admin_login" label="Логин" :value="$values['admin_login']"
                                        :error="$errors['admin_login'] ?? null" required
                                        description="8–64 символа: латиница, цифры и _. Это имя для входа." />
                            <x-md.input id="admin-name" name="admin_name" label="Имя" :value="$values['admin_name']"
                                        :error="$errors['admin_name'] ?? null" required
                                        description="Как показывать тебя в панели." />
                            <x-md.input id="admin-password" name="admin_password" type="password" label="Пароль"
                                        :value="''" :error="$errors['admin_password'] ?? null"
                                        autocomplete="new-password" required
                                        description="Минимум 8 символов и один спецсимвол: !, ?, #." />
                            <x-md.input id="admin-password-confirm" name="admin_password_confirm" type="password"
                                        label="Пароль ещё раз" :value="''"
                                        :error="$errors['admin_password_confirm'] ?? null"
                                        autocomplete="new-password" required />
                            <x-md.select id="timezone" name="timezone" label="Часовой пояс"
                                         :options="array_combine($timezones, $timezones)"
                                         :selected="$values['timezone']" :error="$errors['timezone'] ?? null" required
                                         description="Время событий и журналов." />
                        </div>

                        <div class="md-setup__actions">
                            <x-md.button href="{{ $base }}&step=3" variant="ghost" icon="arrow-left">Назад</x-md.button>
                            <x-md.button type="submit" icon="rocket" size="lg">Установить LaIOT</x-md.button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    @endif
</main>

<footer class="md-setup__foot">LaIOT · LaIOT {{ $version }} · установка</footer>
<script src="/assets/admin/laiot.js" defer></script>
</body>
</html>
