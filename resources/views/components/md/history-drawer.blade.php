@props(['id', 'title' => 'История значения'])
<div x-data="{drawerId: @js($id)}">
    <x-md.button variant="secondary" icon="history" @click="$store.mdDrawer.show(drawerId)">Показать историю</x-md.button>
    <x-md.drawer :id="$id" :title="$title" subtitle="История изменений">
        {{ $slot }}
    </x-md.drawer>
</div>
