@props(['title' => 'Схема вызова', 'nodes' => [], 'startedAt' => null, 'duration' => null, 'compact' => false])
<x-md.card :title="$title" icon="route" {{ $attributes->class(['md-call-flow', 'md-call-flow--compact' => $compact]) }}>
    @if ($nodes === [])<x-md.empty-state title="Нет данных о вызове" compact />@else
        @if ($startedAt || $duration)<div class="md-call-flow__head-meta">@if ($startedAt)<span class="md-call-flow__started">Начало: {{ $startedAt }}</span>@endif @if ($duration)<span class="md-call-flow__duration">{{ $duration }}</span>@endif</div>@endif
        <div class="md-call-flow__body">
        <x-md.call-flow-nodes :nodes="$nodes" :counter="(object) ['value' => 0]" />
        </div>
    @endif
</x-md.card>
