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
<x-md.card :title="$title" icon="route" {{ $attributes->class(['md-call-flow', 'md-call-flow--compact' => $compact]) }}>
    @if ($hasAnyChildren)
        <x-slot:actions>
            <div class="md-call-flow__head-actions">
                <button type="button" class="md-btn md-btn--ghost md-btn--sm md-call-flow__tool-btn" @click="$dispatch('call-flow-collapse')" title="Свернуть все ветки">
                    <x-md.icon name="fold" /> <span class="d-none d-sm-inline">Свернуть всё</span>
                </button>
                <button type="button" class="md-btn md-btn--ghost md-btn--sm md-call-flow__tool-btn" @click="$dispatch('call-flow-expand')" title="Развернуть все ветки">
                    <x-md.icon name="unfold" /> <span class="d-none d-sm-inline">Развернуть всё</span>
                </button>
            </div>
        </x-slot:actions>
    @endif
    @if ($nodes === [])<x-md.empty-state title="Нет данных о вызове" compact />@else
        @if ($startedAt || $duration)<div class="md-call-flow__head-meta">@if ($startedAt)<span class="md-call-flow__started">Начало: {{ $startedAt }}</span>@endif @if ($duration)<span class="md-call-flow__duration">{{ $duration }}</span>@endif</div>@endif
        <div class="md-call-flow__body">
        <x-md.call-flow-nodes :nodes="$nodes" :counter="(object) ['value' => 0]" :max-depth="max(1, min(128, (int)$maxDepth))" />
        </div>
    @endif
</x-md.card>
