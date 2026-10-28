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
        lede="Интеграции и расширения вашего дома."
        :meta="[
            ['icon' => 'puzzle', 'label' => 'Найдено', 'value' => count($modules)],
            ['icon' => 'player-play', 'label' => 'Включено', 'value' => $enabledCount],
        ]" />



    @if ($modules === [])
        <x-md.empty-state icon="puzzle" title="Модулей пока нет"
                          text="Здесь появятся доступные интеграции и расширения." />
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

                        <x-md.spoiler title="Информация о модуле" icon="info-circle">
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
                        </x-md.spoiler>

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
                                          data-md-confirm="Выключить модуль «{{ $module['name'] }}»? Интеграция перестанет работать до включения.">
                                        <input type="hidden" name="_token" value="{{ $csrf }}">
                                        <x-md.button type="submit" variant="secondary" size="sm" icon="player-pause">Выключить</x-md.button>
                                    </form>
                                @else
                                    <form method="post" action="/admin/modules/{{ $module['id'] }}/enable" class="md-inline-form">
                                        <input type="hidden" name="_token" value="{{ $csrf }}">
                                        <x-md.button type="submit" variant="primary" size="sm" icon="player-play">Включить</x-md.button>
                                    </form>
                                @endif
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

    @endif
</div>
