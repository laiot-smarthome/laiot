@props(['name', 'label' => 'Освещение', 'value' => 50, 'enabled' => false])

<x-md.card :title="$label" icon="bulb" {{ $attributes }}>
    <x-md.switch :name="$name . '_enabled'" label="Включено" :checked="$enabled" />
    <x-md.range :name="$name . '_brightness'" label="Яркость" :value="$value" unit="%" />
</x-md.card>
