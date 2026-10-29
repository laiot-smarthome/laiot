@props(['nodes' => [], 'counter' => null, 'depth' => 0, 'maxDepth' => 24])
@php
    $counter = $counter ?? (object) ['value' => 0];
    $kinds = [
        'event' => ['Событие', 'bolt'], 'method' => ['Метод', 'function'],
        'property' => ['Свойство', 'variable'], 'script' => ['Сценарий', 'file-code'],
        'condition' => ['Условие', 'arrows-split-2'], 'timer' => ['Таймер', 'clock'],
        'action' => ['Действие', 'player-play'], 'module' => ['Модуль', 'puzzle'],
        'error' => ['Ошибка', 'alert-circle'], 'note' => ['Заметка', 'info-circle'],
    ];
    $statuses = ['ok' => ['Выполнено', 'success'], 'success' => ['Выполнено', 'success'], 'error' => ['Ошибка', 'danger'], 'warning' => ['Внимание', 'warning'], 'skipped' => ['Пропущено', 'neutral'], 'pending' => ['Ожидание', 'info'], 'running' => ['Выполняется', 'primary']];
@endphp
@if ($depth <= $maxDepth)
<ol class="{{ $depth === 0 ? 'md-call-flow__tree' : 'md-call-flow__children' }}">
    @foreach ($nodes as $node)
        @if (is_array($node))
            @php
                $counter->value++;
                $kind = $node['kind'] ?? $node['type'] ?? 'note';
                [$kindLabel, $kindIcon] = $kinds[$kind] ?? [$kind, 'point'];
                $status = $node['status'] ?? '';
                [$statusLabel, $statusTone] = $statuses[$status] ?? [$status, 'neutral'];
                $hasChildren = !empty($node['children']) || !empty($node['branches']);
                $childCount = count($node['children'] ?? []) + count($node['branches'] ?? []);
            @endphp
            <li @class([
                    'md-call-flow__node',
                    'md-call-flow__node--' . (isset($kinds[$kind]) ? $kind : 'note'),
                    'md-call-flow__node--has-children' => $hasChildren,
                    'is-deep' => $depth >= 3,
                ])
                @if ($hasChildren)
                    x-data="{ open: true }"
                    :class="{ 'is-collapsed': !open, 'is-expanded': open }"
                    @call-flow-set="open = $event.detail.open"
                @endif>
                <div class="md-call-flow__row">
                    <span class="md-call-flow__rail">
                        @if ($hasChildren)
                            <button type="button"
                                    class="md-call-flow__toggle"
                                    @click.stop="open = !open"
                                    :title="open ? 'Свернуть поддерево' : 'Развернуть поддерево'"
                                    :aria-expanded="open.toString()"
                                    aria-label="{{ $kindLabel }}: переключить ветку">
                                <span class="md-call-flow__dot" aria-hidden="true"><x-md.icon :name="$kindIcon" /></span>
                                <span class="md-call-flow__expander" aria-hidden="true" :class="{ 'is-rotated': !open }">
                                    <i class="ti ti-chevron-down" aria-hidden="true"></i>
                                </span>
                            </button>
                        @else
                            <span class="md-call-flow__dot" aria-hidden="true"><x-md.icon :name="$kindIcon" /></span>
                        @endif
                    </span>
                    <div class="md-call-flow__main">
                        <div class="md-call-flow__main-head"><div class="md-call-flow__title">
                            @if (!empty($node['entity']))<x-md.entity :name="$node['entity']['name'] ?? ''" :type="$node['entity']['type'] ?? 'object'" :label="$node['entity']['label'] ?? null" :href="$node['entity']['href'] ?? null" />
                            @elseif (!empty($node['name']))<x-md.entity :name="$node['name']" :type="$node['type'] ?? 'object'" />
                            @else{{ $node['title'] ?? $kindLabel }}@endif
                        </div><div class="md-call-flow__time">@if (!empty($node['time']))<span class="md-call-flow__stamp"><x-md.icon name="clock" />{{ $node['time'] }}</span>@endif @if (!empty($node['offset']))<span class="md-call-flow__offset">{{ $node['offset'] }}</span>@endif</div></div>
                        <div class="md-call-flow__meta">
                            <span class="md-call-flow__step">#{{ $counter->value }}</span>
                            <span class="md-call-flow__kind">{{ $kindLabel }}</span>
                            @if (!empty($node['duration']))<span class="md-call-flow__duration">{{ $node['duration'] }}</span>@endif
                            @if ($statusLabel !== '')<span class="md-call-flow__status md-call-flow__status--{{ $statusTone }}">{{ $statusLabel }}</span>@endif
                            @if (!empty($node['note']))<span class="md-call-flow__note">{{ $node['note'] }}</span>@endif
                            @if ($hasChildren)
                                <button type="button"
                                        x-show="!open"
                                        x-cloak
                                        @click.stop="open = true"
                                        class="md-call-flow__collapsed-tag"
                                        title="Нажмите, чтобы развернуть ветку">
                                    <x-md.icon name="corner-down-right" />
                                    <span>{{ $childCount }} {{ $childCount === 1 ? 'шаг' : ($childCount < 5 ? 'шага' : 'шагов') }}</span>
                                </button>
                            @endif
                        </div>
                        @if (!empty($node['actor']))<div class="md-flow-change__actor">Источник: {{ $node['actor'] }}</div>@endif
                        @if (array_key_exists('before', $node) || array_key_exists('after', $node))<div class="md-flow-change"><span>Было: <strong>{{ $node['before'] ?? '—' }}</strong></span><x-md.icon name="arrow-right" /><span>Стало: <strong>{{ $node['after'] ?? '—' }}</strong></span></div>@endif
                        @if (!empty($node['expression']))<code class="md-call-flow__expression">{{ $node['expression'] }}</code>@endif
                        @if (!empty($node['detail']))<details class="md-call-flow__detail"><summary>Подробности</summary><div class="md-call-flow__detail-body">{{ $node['detail'] }}</div></details>@endif
                        @if (isset($node['payload']))<details class="md-call-flow__detail"><summary>Данные</summary><pre class="md-call-flow__payload">{{ is_array($node['payload']) ? json_encode($node['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $node['payload'] }}</pre></details>@endif
                    </div>
                </div>
                @if (!empty($node['children']))
                    <div @if ($hasChildren) x-show="open" x-cloak class="md-call-flow__subflow" @endif>
                        <x-md.call-flow-nodes :nodes="$node['children']" :counter="$counter" :depth="$depth + 1" :max-depth="$maxDepth" />
                    </div>
                @endif
                @if (!empty($node['branches']))
                    <div @if ($hasChildren) x-show="open" x-cloak class="md-call-flow__subflow" @endif>
                        <div class="md-call-flow__branches">@foreach ($node['branches'] as $branch)
                            <div @class(['md-call-flow__branch', 'is-taken' => $branch['taken'] ?? true, 'is-skipped' => !($branch['taken'] ?? true), 'md-call-flow__branch--has-children' => !empty($branch['nodes'])])>
                                <div class="md-call-flow__branch-head">
                                    <span class="md-call-flow__branch-label">{{ $branch['label'] ?? 'Ветка' }}</span>
                                    <span class="md-call-flow__branch-state">{{ ($branch['taken'] ?? true) ? 'выполнено' : 'не выполнялось' }}</span>
                                </div>
                                @if (!empty($branch['note']))<span class="md-call-flow__branch-note">{{ $branch['note'] }}</span>@endif
                                @if (!empty($branch['nodes']))
                                    <div class="md-call-flow__branch-subflow">
                                        <x-md.call-flow-nodes :nodes="$branch['nodes']" :counter="$counter" :depth="$depth + 1" :max-depth="$maxDepth" />
                                    </div>
                                @elseif (!empty($branch['empty_text']))<p class="md-call-flow__branch-empty">{{ $branch['empty_text'] }}</p>@endif
                            </div>
                        @endforeach</div>
                    </div>
                @endif
            </li>
        @endif
    @endforeach
</ol>
@endif
