@php
    $tabs = [];
    foreach ($groups as $groupName => $count) {
        $tabs[] = [
            'id' => (string)$groupName,
            'label' => (string)($groupLabels[$groupName] ?? $groupName),
            'count' => (int)$count,
            'href' => '/settings?group=' . rawurlencode((string)$groupName),
        ];
    }
    $activeLabel = $groupLabels[$activeGroup] ?? $activeGroup;
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Система"
        icon="settings"
        title="Настройки"
        lede="Параметры установки из таблицы settings: их читают ядро и сценарии через Config/Setting."
        :meta="[
            ['icon' => 'list', 'label' => 'Параметров', 'value' => $total],
            ['icon' => 'category', 'label' => 'Разделов', 'value' => count($groups)],
        ]" />

    @if ($done ?? false)
        <x-md.alert type="success" title="Настройка сохранена" text="Новое значение применится при следующем запросе." />
    @endif

    @if ($search === '' && count($tabs) > 1)
        <x-md.tabs :items="$tabs" :active="$activeGroup" label="Разделы настроек" />
    @endif

    <x-md.table label="Настройки">
        <x-slot:toolbar>
            <form class="md-ui-search" method="get" action="/settings" role="search">
                <input class="md-input" type="search" name="q" value="{{ $search }}"
                       placeholder="Поиск по имени и заголовку" aria-label="Поиск настроек" autocomplete="off">
                <button class="md-btn md-btn--secondary" type="submit">
                    <i class="ti ti-search" aria-hidden="true"></i><span class="md-btn__label">Найти</span>
                </button>
                @if ($search !== '')
                    <a class="md-btn md-btn--ghost" href="/settings">Сбросить</a>
                @endif
            </form>
            <div class="md-table__meta">
                @if ($search !== '')
                    Найдено: {{ count($settings) }}
                @else
                    {{ $activeGroup !== '' ? $activeLabel : 'Все' }}: {{ count($settings) }}
                @endif
            </div>
        </x-slot:toolbar>

        @if ($settings === [])
            <x-slot:empty>
                <div class="md-empty">
                    <span class="md-empty__icon"><i class="ti ti-settings" aria-hidden="true"></i></span>
                    <div class="md-empty__title">{{ $search !== '' ? 'Настройки не найдены' : 'Настроек пока нет' }}</div>
                    <p class="md-empty__text">{{ $search !== '' ? 'Измените запрос или сбросьте поиск.' : 'Параметры появляются после установки и миграций.' }}</p>
                </div>
            </x-slot:empty>
        @else
            <x-slot:head>
                <th scope="col">Параметр</th>
                <th scope="col">Имя</th>
                <th scope="col">Тип</th>
                <th scope="col">Значение</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>

            <x-slot:body>
                @foreach ($settings as $setting)
                    <tr>
                        <td>
                            <div class="md-table__name">
                                <span class="md-table__icon"><i class="ti ti-settings" aria-hidden="true"></i></span>
                                <div><strong>{{ $setting['title'] !== '' ? $setting['title'] : $setting['name'] }}</strong></div>
                            </div>
                        </td>
                        <td><code class="md-table__code">{{ $setting['name'] }}</code></td>
                        <td><x-md.badge tone="outline">{{ $setting['type'] }}</x-md.badge></td>
                        <td>
                            @if ($setting['value'] !== '')
                                <span class="md-table__value" title="{{ $setting['value'] }}">{{ $setting['value'] }}</span>
                            @else
                                <span class="md-table__muted">не задано</span>
                            @endif
                        </td>
                        <td>
                            <div class="md-table__actions">
                                <x-md.icon-button href="/settings/{{ $setting['id'] }}/edit" icon="pencil" label="Изменить параметр" size="sm" />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        @endif
    </x-md.table>
</div>
