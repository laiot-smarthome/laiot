@props(['name', 'label' => 'Термостат', 'temperature' => null, 'target' => 22, 'min' => 16, 'max' => 30, 'step' => 0.5])

<x-md.card :title="$label" icon="temperature" {{ $attributes }}>
    @if ($temperature !== null)<p>Сейчас: <strong>{{ $temperature }} °C</strong></p>@endif
    <x-md.range :name="$name" label="Целевая температура" :value="$target" :min="$min" :max="$max" :step="$step" unit=" °C" />
</x-md.card>
