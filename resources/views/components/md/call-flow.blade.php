@props(['title' => 'Схема вызова', 'nodes' => []])
<x-md.card :title="$title" icon="route" {{ $attributes }}>
    @if ($nodes === [])<x-md.empty-state title="Нет данных о вызове" compact />@else
        <x-md.call-flow-nodes :nodes="$nodes" />
    @endif
</x-md.card>
