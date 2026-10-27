@props(['name', 'description' => null, 'icon' => 'sparkles', 'enabled' => true])

<x-md.card :title="$name" :subtitle="$description" :icon="$icon" {{ $attributes }}>
    <div class="md-ui-actions"><x-md.badge :tone="$enabled ? 'success' : 'neutral'">{{ $enabled ? 'Включён' : 'Выключен' }}</x-md.badge>{{ $slot }}</div>
</x-md.card>
