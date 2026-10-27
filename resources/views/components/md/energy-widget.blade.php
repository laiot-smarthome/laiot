@props(['today', 'yesterday' => null, 'unit' => 'кВт·ч', 'usage' => []])
<x-md.card title="Энергопотребление" icon="bolt" {{ $attributes }}>
    <p class="md-kit-energy-value">{{ $today }} <small>{{ $unit }}</small></p>
    @if ($yesterday !== null)<p class="md-table__muted">Вчера: {{ $yesterday }} {{ $unit }}</p>@endif
    @foreach ($usage as $item)<x-md.meter :label="$item['label']" :value="$item['value']" :max="$item['max'] ?? 100" :unit="$item['unit'] ?? '%'" />@endforeach
</x-md.card>
