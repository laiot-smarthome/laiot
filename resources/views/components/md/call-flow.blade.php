@props(['title' => 'Схема вызова', 'nodes' => [], 'startedAt' => null, 'duration' => null, 'compact' => false, 'maxDepth' => 24])
@php
    $hasAnyChildren = false;
    $scanChildren = static function (array $items) use (&$scanChildren, &$hasAnyChildren): void {
        foreach ($items as $item) {
            if (is_array($item)) {
                if (!empty($item['children']) || !empty($item['branches'])) {
                    $hasAnyChildren = true;
                    return;
                }
            }
        }
    };
    $scanChildren($nodes);
@endphp
<x-md.card :title="$title" icon="route" x-data="mdCallFlow"
           @pointerover="highlight($event.target)" @pointerleave="highlight(null)"
           @focusin="highlight($event.target, 'focus')"
           @focusout="$nextTick(() => highlight($el.contains(document.activeElement) ? document.activeElement : null, 'focus'))"
           {{ $attributes->class(['md-call-flow', 'md-call-flow--compact' => $compact]) }}>
    @if ($hasAnyChildren)
        <x-slot:actions>
            <div class="md-call-flow__head-actions">
                <button type="button" class="md-btn md-btn--ghost md-btn--sm md-call-flow__tool-btn" @click="setExpanded(false)" title="Свернуть все ветки">
                    <x-md.icon name="fold" /> <span class="d-none d-sm-inline">Свернуть всё</span>
                </button>
                <button type="button" class="md-btn md-btn--ghost md-btn--sm md-call-flow__tool-btn" @click="setExpanded(true)" title="Развернуть все ветки">
                    <x-md.icon name="unfold" /> <span class="d-none d-sm-inline">Развернуть всё</span>
                </button>
            </div>
        </x-slot:actions>
    @endif
    @if ($nodes === [])<x-md.empty-state title="Нет данных о вызове" compact />@else
        @if ($startedAt || $duration)<div class="md-call-flow__head-meta">@if ($startedAt)<span class="md-call-flow__started">Начало: {{ $startedAt }}</span>@endif @if ($duration)<span class="md-call-flow__duration">{{ $duration }}</span>@endif</div>@endif
        <div class="md-call-flow__body" x-ref="flowBody">
            <svg class="md-call-flow__links" x-ref="links" aria-hidden="true" focusable="false" width="0" height="0">
                <g class="md-call-flow__links-base" x-ref="baseLinks"></g>
                <g class="md-call-flow__links-active" x-ref="activeLinks"></g>
            </svg>
        <div class="md-call-flow__forest" x-ref="forest">
        <x-md.call-flow-nodes :nodes="$nodes" :counter="(object) ['value' => 0]" :max-depth="max(1, min(128, (int)$maxDepth))" />
        </div>
        </div>
    @endif
</x-md.card>
