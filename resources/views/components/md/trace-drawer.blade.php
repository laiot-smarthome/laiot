@props(['id', 'title' => 'Цепочка изменений', 'nodes' => []])
<div x-data="{drawerId: @js($id)}">
    <x-md.button variant="secondary" icon="route" @click="$store.mdDrawer.show(drawerId)">Показать цепочку</x-md.button>
    <x-md.drawer :id="$id" :title="$title" subtitle="Причины и последствия события">
        <x-md.call-flow title="Шаги выполнения" :nodes="$nodes" />
    </x-md.drawer>
</div>
