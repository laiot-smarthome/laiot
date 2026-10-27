@props(['name', 'state' => 'idle', 'description' => null, 'updated' => null])
<x-md.card :title="$name" :subtitle="$description" icon="refresh" {{ $attributes }}>
    <div class="md-ui-actions"><x-md.status :state="$state" /> @if ($updated !== null)<x-md.last-seen label="Обновлено" :value="$updated" />@endif {{ $slot }}</div>
</x-md.card>
