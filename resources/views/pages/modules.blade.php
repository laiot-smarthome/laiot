@php
    $enabledCount = 0;
    foreach ($modules as $module) {
        if ($module['enabled']) {
            $enabledCount++;
        }
    }
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Система"
        icon="puzzle"
        title="Модули"
        lede="SDK-модули: MQTT, Zigbee и сторонние расширения. Ядровые разделы — объекты, сценарии, пользователи — модулями не являются."
        :meta="[
            ['icon' => 'puzzle', 'label' => 'Найдено', 'value' => count($modules)],
            ['icon' => 'player-play', 'label' => 'Включено', 'value' => $enabledCount],
        ]" />

    @if ($message !== '')
        <x-md.alert type="success" :text="$message" />
    @endif
    @if ($error !== '')
        <x-md.alert type="danger" title="Действие не выполнено" :text="$error" />
    @endif

    <x-md.hint tone="info" title="Ядро и модули — разные вещи"
               :steps="[
                   'Объекты, классы, свойства, методы, сценарии, события, пользователи, настройки, журналы и API живут в ядре и не имеют module.json.',
                   'SDK-модуль объявляет manifest, миграции, маршруты и меню; его можно установить, выключить или удалить.',
                   'Жизненный цикл выполняется теми же сервисами, что и CLI: module:install, module:enable, module:disable, module:remove.',
               ]" />

    @if ($modules === [])
        <x-md.empty-state icon="puzzle" title="SDK-модулей нет"
                          text="В modules/ нет ни одного module.json. Ядровые разделы доступны в меню слева." />
    @else
        <div class="md-modules-grid">
            @foreach ($modules as $module)
                @php
                    $isCore = (bool)$module['core'];
                    $installed = (bool)$module['installed'];
                    $enabled = (bool)$module['enabled'];
                @endphp
                <section class="md-card">
                    <div class="md-card__body">
                        <div class="md-ui-actions">
                            <x-md.badge :tone="$isCore ? 'info' : 'outline'">
                                {{ $isCore ? 'ядро' : $module['type'] }}
                            </x-md.badge>
                            <x-md.badge tone="neutral">v{{ $module['version'] }}</x-md.badge>
                            @if ($enabled)
                                <x-md.badge tone="success" dot>включён</x-md.badge>
                            @elseif ($installed)
                                <x-md.badge tone="warning" dot>выключен</x-md.badge>
                            @else
                                <x-md.badge tone="danger" dot>не установлен</x-md.badge>
                            @endif
                        </div>

                        <h2 class="md-card__title">{{ $module['name'] }}</h2>
                        @if ($module['description'] !== '')
                            <p class="md-card__subtitle">{{ $module['description'] }}</p>
                        @endif

                        <dl class="md-dash-kv">
                            <div><dt>ID</dt><dd><code class="md-table__code">{{ $module['id'] }}</code></dd></div>
                            <div><dt>Маршрут</dt><dd><code class="md-table__code">/module/{{ $module['id'] }}</code></dd></div>
                            @if ($module['tables'] !== [])
                                <div><dt>Таблицы</dt><dd>{{ count($module['tables']) }}</dd></div>
                            @endif
                            @if ($module['tasks'] !== [])
                                <div><dt>Фоновые задачи</dt><dd>{{ count($module['tasks']) }}</dd></div>
                            @endif
                        </dl>

                        <div class="md-ui-actions">
                            @if ($enabled)
                                <x-md.button :href="'/module/' . $module['id']" variant="secondary" size="sm" icon="arrow-right">Открыть</x-md.button>
                            @endif

                            @if (!$isCore && $installed && ($module['settings'] !== [] || $module['subscriber']))
                                <x-md.button :href="'/admin/modules/' . $module['id'] . '/settings'" variant="secondary" size="sm" icon="settings">Настройки</x-md.button>
                            @endif

                            @if ($isCore)
                                @if ($enabled)
                                    <form method="post" action="/admin/modules/{{ $module['id'] }}/disable" class="md-inline-form"
                                          data-md-confirm="Выключить ядровый модуль «{{ $module['name'] }}»? Его страницы и фоновая задача перестанут работать до включения.">
                                        <input type="hidden" name="_token" value="{{ $csrf }}">
                                        <x-md.button type="submit" variant="secondary" size="sm" icon="player-pause">Выключить</x-md.button>
                                    </form>
                                @else
                                    <form method="post" action="/admin/modules/{{ $module['id'] }}/enable" class="md-inline-form">
                                        <input type="hidden" name="_token" value="{{ $csrf }}">
                                        <x-md.button type="submit" variant="primary" size="sm" icon="player-play">Включить</x-md.button>
                                    </form>
                                @endif
                                <span class="md-table__muted">Ядровый модуль входит в поставку: удалить его нельзя.</span>
                            @elseif (!$installed)
                                <form method="post" action="/admin/modules/{{ $module['id'] }}/install" class="md-inline-form">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.button type="submit" icon="download" size="sm">Установить</x-md.button>
                                </form>
                            @else
                                @if ($enabled)
                                    <form method="post" action="/admin/modules/{{ $module['id'] }}/disable" class="md-inline-form">
                                        <input type="hidden" name="_token" value="{{ $csrf }}">
                                        <x-md.button type="submit" variant="secondary" size="sm" icon="player-pause">Выключить</x-md.button>
                                    </form>
                                @else
                                    <form method="post" action="/admin/modules/{{ $module['id'] }}/enable" class="md-inline-form">
                                        <input type="hidden" name="_token" value="{{ $csrf }}">
                                        <x-md.button type="submit" variant="primary" size="sm" icon="player-play">Включить</x-md.button>
                                    </form>
                                @endif
                                <form method="post" action="/admin/modules/{{ $module['id'] }}/remove" class="md-inline-form"
                                      data-md-confirm="Удалить модуль «{{ $module['name'] }}» из реестра? Данные модуля останутся в его таблицах.">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.button type="submit" variant="danger" size="sm" icon="trash">Удалить</x-md.button>
                                </form>
                            @endif
                        </div>
                    </div>
                </section>
            @endforeach
        </div>

        <x-md.hint tone="tip" title="То же самое из терминала"
                   text="php bin/mydomo module:list · module:install <id> · module:enable <id> · module:disable <id> · module:remove <id>. Перед удалением модуля сделайте резервную копию его таблиц." />
    @endif
</div>
