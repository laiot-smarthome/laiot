@php
    $field = static fn (string $key): array => $schema[$key] ?? ['title' => $key, 'description' => ''];
    $value = static fn (string $key, mixed $default = '') => $values[$key] ?? $default;
    $connected = (bool)($status['connected'] ?? false);
    $alive = (bool)($status['worker_alive'] ?? false);
@endphp

<div class="md-ui-page md-ui-page--stack md-mqtt-page md-mqtt-settings-page" data-md-mqtt-live>
    <x-md.page-hero
        eyebrow="MQTT"
        icon="settings"
        title="Настройки MQTT"
        lede="Подключение к брокеру, авторизация, TLS и поведение приёма. После сохранения worker переподключится сам."
        :meta="[
            ['icon' => 'router', 'label' => 'Текущий брокер', 'value' => (string)$value('host') . ':' . (string)$value('port')],
            ['icon' => 'filter', 'label' => 'Подписки', 'value' => (string)$value('query')],
        ]">
        <x-slot:actions>
            <x-md.button href="/module/mqtt" variant="secondary" icon="arrow-left">К топикам</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    @if ($done !== '')
        <x-md.alert type="success" :text="$done" />
    @endif
    @if ($error !== '')
        <x-md.alert type="danger" title="Настройки не сохранены" :text="$error" />
    @endif

    <div class="md-mqtt-connection">
        <x-md.badge :tone="$connected ? 'success' : 'warning'" dot>{{ $connected ? 'брокер подключён' : 'нет соединения' }}</x-md.badge>
        <x-md.badge :tone="$alive ? 'success' : 'danger'" dot>{{ $alive ? 'worker mqtt запущен' : 'worker mqtt не запущен' }}</x-md.badge>
        <span class="md-table__muted">Соединение проверяется кнопкой ниже по сохранённым значениям.</span>
    </div>

    @yoyo('mqtt-status')

    <form method="post" action="/module/mqtt/settings" class="md-ui-form" autocomplete="off">
        <input type="hidden" name="_token" value="{{ $csrf }}">
        <div class="md-ui-form-stack">
            <section class="md-card">
                <header class="md-card__head">
                    <div class="md-card__titles">
                        <div class="md-card__eyebrow">Подключение</div>
                        <h2 class="md-card__title">Брокер</h2>
                        <p class="md-card__subtitle">Адрес, порт и подписки, из которых модуль получает сообщения.</p>
                    </div>
                </header>
                <div class="md-card__body">
                    <div class="md-ui-form-stack">
                        <div class="md-ui-form-grid">
                            <x-md.input name="client_id" :label="$field('client_id')['title']" :value="(string)$value('client_id')"
                                        :hint="$field('client_id')['description']" placeholder="MyDoMo MQTT" />
                            <x-md.input name="host" :label="$field('host')['title']" :value="(string)$value('host')" required
                                        :hint="$field('host')['description']" placeholder="localhost" />
                        </div>
                        <div class="md-ui-form-grid">
                            <x-md.input type="number" name="port" :label="$field('port')['title']" :value="(string)$value('port')"
                                        :hint="$field('port')['description']" min="1" max="65535" />
                            <x-md.input type="number" name="keepalive" :label="$field('keepalive')['title']" :value="(string)$value('keepalive')"
                                        :hint="$field('keepalive')['description']" min="1" max="600" />
                        </div>
                        <x-md.textarea name="query" :label="$field('query')['title']" :value="(string)$value('query')" :rows="3"
                                       :hint="$field('query')['description']" placeholder="/var/now/#" />
                    </div>
                </div>
            </section>

            <section class="md-card">
                <header class="md-card__head">
                    <div class="md-card__titles">
                        <div class="md-card__eyebrow">Безопасность</div>
                        <h2 class="md-card__title">Авторизация и TLS</h2>
                        <p class="md-card__subtitle">Логин и пароль брокера, а также параметры защищённого соединения.</p>
                    </div>
                </header>
                <div class="md-card__body">
                    <div class="md-ui-form-stack">
                        <x-md.checkbox name="auth" :label="$field('auth')['title']" :checked="(bool)$value('auth', false)"
                                       :description="$field('auth')['description']" />
                        <div class="md-ui-form-grid">
                            <x-md.input name="username" :label="$field('username')['title']" :value="(string)$value('username')"
                                        :hint="$field('username')['description']" autocomplete="off" />
                            <x-md.input type="password" name="password" :label="$field('password')['title']" :value="(string)$value('password')"
                                        :hint="$field('password')['description']" passwordToggle autocomplete="new-password" />
                        </div>
                        <hr class="md-mqtt-divider">
                        <x-md.checkbox name="tls" :label="$field('tls')['title']" :checked="(bool)$value('tls', false)"
                                       :description="$field('tls')['description']" />
                        <div class="md-ui-form-grid">
                            <x-md.input name="tls_ca" :label="$field('tls_ca')['title']" :value="(string)$value('tls_ca')"
                                        :hint="$field('tls_ca')['description']" placeholder="/etc/ssl/certs/broker-ca.pem" />
                            <x-md.checkbox name="tls_verify" :label="$field('tls_verify')['title']" :checked="(bool)$value('tls_verify', true)"
                                           :description="$field('tls_verify')['description']" />
                        </div>
                    </div>
                </div>
            </section>

            <section class="md-card">
                <header class="md-card__head">
                    <div class="md-card__titles">
                        <div class="md-card__eyebrow">Поведение</div>
                        <h2 class="md-card__title">Приём и история</h2>
                        <p class="md-card__subtitle">Фильтрация несвязанных топиков, подавление повторов и срок журнала.</p>
                    </div>
                </header>
                <div class="md-card__body">
                    <div class="md-ui-form-stack">
                        <x-md.checkbox name="stripmode" :label="$field('stripmode')['title']" :checked="(bool)$value('stripmode', false)"
                                       :description="$field('stripmode')['description']" />
                        <div class="md-ui-form-grid">
                            <x-md.input type="number" name="unlinked_interval" :label="$field('unlinked_interval')['title']"
                                        :value="(string)$value('unlinked_interval')" min="0"
                                        :hint="$field('unlinked_interval')['description']" />
                            <x-md.input type="number" name="dedup_seconds" :label="$field('dedup_seconds')['title']"
                                        :value="(string)$value('dedup_seconds')" min="0"
                                        :hint="$field('dedup_seconds')['description']" />
                        </div>
                        <div class="md-ui-form-grid">
                            <x-md.input type="number" name="history_keep_days" :label="$field('history_keep_days')['title']"
                                        :value="(string)$value('history_keep_days')" min="1" max="3650"
                                        :hint="$field('history_keep_days')['description']" />
                            <x-md.checkbox name="debug" :label="$field('debug')['title']" :checked="(bool)$value('debug', false)"
                                           :description="$field('debug')['description']" />
                        </div>
                    </div>
                </div>
            </section>

            <div class="md-ui-form-actions">
                <x-md.button type="submit" icon="check">Сохранить настройки</x-md.button>
                <x-md.button href="/module/mqtt" variant="secondary">Отмена</x-md.button>
            </div>
        </div>
    </form>

    <div class="md-mqtt-settings-footer">
        <section class="md-card">
            <header class="md-card__head">
                <div class="md-card__titles">
                    <div class="md-card__eyebrow">Проверка</div>
                    <h2 class="md-card__title">Соединение с брокером</h2>
                    <p class="md-card__subtitle">Проверяются сохранённые значения: сначала сохраните форму, затем проверяйте.</p>
                </div>
            </header>
            <div class="md-card__body">
                <form method="post" action="/module/mqtt/settings/test" class="md-inline-form">
                    <input type="hidden" name="_token" value="{{ $csrf }}">
                    <x-md.button type="submit" variant="secondary" icon="plug-connected">Проверить соединение</x-md.button>
                </form>
            </div>
        </section>

        <x-md.hint
            tone="info"
            title="Фоновый процесс"
            :steps="[
                'Приём и отправка работают в worker mqtt: php bin/mydomo worker mqtt.',
                'В systemd используется шаблон mydomo-worker@mqtt.service.',
                'Изменения настроек подхватываются автоматически в течение нескольких секунд; после правки кода модуля worker нужно перезапустить.',
            ]" />
    </div>
</div>

@include('mqtt::partials.live')
