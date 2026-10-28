@php
    $format = static fn (string $value): string => $value === '' ? '—' : date('d.m H:i:s', (int)strtotime($value));
    $relative = static function (string $value): string {
        if ($value === '') {
            return '—';
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '—';
        }
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'только что';
        }
        if ($diff < 3600) {
            return max(1, (int)floor($diff / 60)) . ' мин назад';
        }
        if ($diff < 86400) {
            return max(1, (int)floor($diff / 3600)) . ' ч назад';
        }

        return date('d.m H:i', $timestamp);
    };
    $freshness = static function (string $value): string {
        if ($value === '') {
            return 'empty';
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return 'stale';
        }
        $diff = time() - $timestamp;

        return $diff <= 3600 ? 'fresh' : ($diff <= 86400 ? 'recent' : 'stale');
    };
    $preview = static function (string $value, int $limit = 120): string {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit, 'UTF-8') . '…';
    };
    $rows = $result['rows'];
    $pages = (int)$result['pages'];
    $current = (int)$result['page'];
    $total = (int)$result['total'];
    $from = $total > 0 ? (($current - 1) * (int)$result['per_page']) + 1 : 0;
    $to = min($current * (int)$result['per_page'], $total);
@endphp

<div class="md-mqtt-kpis md-ui-kpi-grid">
    <x-md.kpi label="Всего топиков" :value="$stats['total']" icon="list" tone="primary" meta="В журнале брокера" />
    <x-md.kpi label="Устройств" :value="$stats['devices']" icon="device-desktop" tone="info" :meta="'С привязкой: ' . $stats['linked']" />
    <x-md.kpi label="Без привязки" :value="$stats['unlinked']" icon="unlink" tone="warning" meta="Кандидаты на очистку" />
    <x-md.kpi label="Активны за 24 ч" :value="$stats['active']" icon="bolt" tone="success" :meta="'Последнее: ' . ($stats['last_update'] !== '' ? $relative($stats['last_update']) : '—')" />
</div>

<div class="md-mqtt-workspace">
    <aside class="md-card md-mqtt-sidebar">
        <header class="md-card__head md-mqtt-sidebar__head">
            <div class="md-card__titles">
                <div class="md-card__eyebrow">Навигация</div>
                <h2 class="md-card__title">Группы</h2>
            </div>
            <div class="md-ui-actions">
                <button type="button" class="md-btn md-btn--sm {{ $groupBy === 'device' ? 'md-btn--primary' : 'md-btn--ghost' }}"
                        yoyo:get="setMode" yoyo:val.mode="device" yoyo:spinning.attr="disabled">Устройства</button>
                <button type="button" class="md-btn md-btn--sm {{ $groupBy === 'path' ? 'md-btn--primary' : 'md-btn--ghost' }}"
                        yoyo:get="setMode" yoyo:val.mode="path" yoyo:spinning.attr="disabled">Пути</button>
            </div>
        </header>
        <div class="md-card__body md-mqtt-sidebar__body">
            <label class="md-mqtt-group-search" for="mdMqttGroupSearch">
                <x-md.icon name="search" />
                <input type="search" id="mdMqttGroupSearch" name="groupSearch" value="{{ $groupSearch }}"
                       placeholder="Найти устройство или путь" autocomplete="off"
                       yoyo:on="keyup delay:300ms changed">
            </label>

            <div class="md-mqtt-group-list">
                <button type="button" class="md-mqtt-group {{ $group === '' ? 'is-active' : '' }}"
                        yoyo:get="selectGroup" yoyo:val.group-key="" yoyo:spinning.attr="disabled">
                    <span class="md-mqtt-group__icon"><x-md.icon name="layout-grid" /></span>
                    <span class="md-mqtt-group__main"><strong>Все топики</strong><small>Весь журнал</small></span>
                    <span class="md-mqtt-group__count">{{ $stats['total'] }}</span>
                </button>
                <button type="button" class="md-mqtt-group {{ $group === '__unlinked__' ? 'is-active' : '' }}"
                        yoyo:get="selectGroup" yoyo:val.group-key="__unlinked__" yoyo:spinning.attr="disabled">
                    <span class="md-mqtt-group__icon is-warning"><x-md.icon name="unlink" /></span>
                    <span class="md-mqtt-group__main"><strong>Без привязки</strong><small>Без объекта и свойства</small></span>
                    <span class="md-mqtt-group__count">{{ $stats['unlinked'] }}</span>
                </button>

                @foreach ($groups['groups'] as $item)
                    <button type="button" class="md-mqtt-group {{ $group === $item['key'] ? 'is-active' : '' }}"
                            yoyo:get="selectGroup" yoyo:val.group-key="{{ $item['key'] }}" yoyo:spinning.attr="disabled">
                        <span class="md-mqtt-group__icon"><x-md.icon :name="$groupBy === 'path' ? 'folder' : 'device-desktop'" /></span>
                        <span class="md-mqtt-group__main">
                            <strong>{{ $item['title'] }}</strong>
                            <small>{{ $groupBy === 'path' ? 'ветка пути' : 'устройство' }}</small>
                        </span>
                        <span class="md-mqtt-group__count">{{ $item['count'] }}</span>
                    </button>
                @endforeach
            </div>

            @if ($groups['limited'])
                <p class="md-table__muted">Показаны первые 300 групп. Уточните поиск.</p>
            @endif
        </div>
    </aside>

    <section class="md-card md-mqtt-main">
        <header class="md-card__head">
            <div class="md-card__titles">
                <div class="md-card__eyebrow">Топики</div>
                <h2 class="md-card__title">{{ $selectedTitle }}</h2>
                <p class="md-card__subtitle">{{ $selectedMeta }} · {{ $total }} {{ $total === 1 ? 'запись' : 'записей' }}</p>
            </div>
            <div class="md-card__actions">
                <div yoyo:spinning role="status" class="md-mqtt-spinner"><span class="md-spinner" aria-hidden="true"></span> Обновляем…</div>
            </div>
        </header>
        <div class="md-card__body">
            <div class="md-mqtt-toolbar">
                <div class="md-ui-search">
                    <input class="md-input" type="search" name="search" value="{{ $search }}"
                           placeholder="Топик, значение, объект или свойство" aria-label="Поиск топиков"
                           autocomplete="off" yoyo:on="keyup delay:300ms changed">
                </div>
                <select class="md-select md-mqtt-toolbar__select" name="linked" aria-label="Привязка" yoyo>
                    <option value="" @selected($linked === '')>Все привязки</option>
                    <option value="1" @selected($linked === '1')>С привязкой</option>
                    <option value="0" @selected($linked === '0')>Без привязки</option>
                </select>
                <select class="md-select md-mqtt-toolbar__select" name="locationId" aria-label="Локация" yoyo>
                    <option value="0" @selected((int)$locationId === 0)>Все локации</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location['id'] }}" @selected((int)$locationId === $location['id'])>{{ $location['title'] }}</option>
                    @endforeach
                </select>
                <select class="md-select md-mqtt-toolbar__select" name="sort" aria-label="Сортировка" yoyo>
                    <option value="updated" @selected($sort === 'updated')>Сначала свежие</option>
                    <option value="path" @selected($sort === 'path')>По пути</option>
                    <option value="title" @selected($sort === 'title')>По названию</option>
                    <option value="value" @selected($sort === 'value')>По значению</option>
                </select>
                <button type="button" class="md-btn md-btn--ghost md-btn--sm" yoyo:get="resetFilters"
                        yoyo:spinning.attr="disabled">Сбросить</button>
            </div>

            @if ($rows === [])
                <x-md.empty-state icon="radio-off" title="Топики не найдены"
                                  text="Брокер ещё не присылал сообщений или фильтры слишком узкие. Проверьте подписки в настройках." />
            @else
                <div class="md-table__scroll">
                    <table class="md-table md-mqtt-table">
                        <thead>
                        <tr>
                            <th>Топик</th>
                            <th>Значение</th>
                            <th>Привязка</th>
                            <th>Обновлено</th>
                            <th><span class="visually-hidden">Действия</span></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($rows as $row)
                            @php
                                $path = (string)$row['path'];
                                $title = trim((string)$row['title']);
                                $display = $title !== '' && $title !== $path ? $title : $path;
                                $linkedObject = (string)$row['linked_object'];
                                $linkedTarget = (string)$row['linked_property'] !== '' ? (string)$row['linked_property'] : (string)$row['linked_method'];
                                $linkedKind = (string)$row['linked_property'] !== '' ? 'свойство' : ((string)$row['linked_method'] !== '' ? 'метод' : '');
                                $write = (string)$row['path_write'];
                                $value = (string)$row['value'];
                                $updated = (string)$row['updated'];
                            @endphp
                            <tr>
                                <td class="md-mqtt-topic-cell">
                                    <a class="md-mqtt-topic-link" href="/module/mqtt/topics/{{ (int)$row['id'] }}/edit">
                                        <span class="md-mqtt-topic-title">{{ $display }}</span>
                                        <span class="md-mqtt-topic-path">{{ $path }}</span>
                                    </a>
                                    <span class="md-mqtt-flags">
                                        <span class="md-badge md-badge--outline">{{ $row['readonly'] ? 'только чтение' : 'чтение/запись' }}</span>
                                        @if ($write !== '')
                                            <span class="md-badge md-badge--info" title="Команда уходит в {{ $write }}">команда → {{ $write }}</span>
                                        @endif
                                        @if ($row['qos'] > 0)
                                            <span class="md-badge md-badge--neutral">QoS {{ (int)$row['qos'] }}</span>
                                        @endif
                                        @if ($row['retain'])
                                            <span class="md-badge md-badge--neutral">retain</span>
                                        @endif
                                        @if ($row['logging'])
                                            <span class="md-badge md-badge--neutral">журнал</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="md-mqtt-value-cell">
                                    @if ($value === '')
                                        <span class="md-table__muted">пусто</span>
                                    @else
                                        <code class="md-mqtt-value" title="{{ $preview($value, 600) }}">{{ $preview($value) }}</code>
                                    @endif
                                </td>
                                <td>
                                    @if ($linkedObject === '')
                                        <span class="md-table__muted">нет</span>
                                    @else
                                        <span class="md-mqtt-link-target"><x-md.icon name="link" />{{ $linkedObject }}<span class="md-table__muted"> · {{ $linkedKind }}: {{ $linkedTarget }}</span></span>
                                    @endif
                                </td>
                                <td class="md-mqtt-updated-cell">
                                    <span class="md-mqtt-freshness md-mqtt-freshness--{{ $freshness($updated) }}">
                                        <span class="md-mqtt-freshness__dot"></span>{{ $relative($updated) }}
                                    </span>
                                    <span class="md-table__muted">{{ $format($updated) }}</span>
                                </td>
                                <td class="md-mqtt-row-actions">
                                    <x-md.icon-button :href="'/module/mqtt/topics/' . (int)$row['id'] . '/edit'" icon="pencil" label="Изменить топик" />
                                    <form method="post" action="/module/mqtt/topics/{{ (int)$row['id'] }}/delete" class="md-inline-form"
                                          data-md-confirm="Удалить топик «{{ $path }}» и его историю?">
                                        <input type="hidden" name="_token" value="{{ $csrf }}">
                                        <x-md.icon-button type="submit" icon="trash" label="Удалить топик" variant="danger" />
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="md-table__footer md-mqtt-pagination">
                    <span class="md-table__muted">{{ $from }}–{{ $to }} из {{ $total }}</span>
                    <div class="md-mqtt-pagination__pages">
                        <button type="button" class="md-btn md-btn--secondary md-btn--sm" yoyo:get="paginate"
                                yoyo:val.page="{{ max(1, $current - 1) }}" @disabled($current <= 1)>Назад</button>
                        @for ($number = 1; $number <= $pages; $number++)
                            @if ($number === 1 || $number === $pages || abs($number - $current) <= 2)
                                <button type="button"
                                        class="md-btn md-btn--sm {{ $number === $current ? 'md-btn--primary' : 'md-btn--ghost' }}"
                                        yoyo:get="paginate" yoyo:val.page="{{ $number }}"
                                        @if ($number === $current) aria-current="page" @endif>{{ $number }}</button>
                            @elseif ($number === 2 || $number === $pages - 1)
                                <span class="md-table__muted">…</span>
                            @endif
                        @endfor
                        <button type="button" class="md-btn md-btn--secondary md-btn--sm" yoyo:get="paginate"
                                yoyo:val.page="{{ min($pages, $current + 1) }}" @disabled($current >= $pages)>Далее</button>
                    </div>
                </div>
            @endif
        </div>
    </section>
</div>
