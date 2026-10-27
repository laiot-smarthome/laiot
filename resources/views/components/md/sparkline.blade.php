@props(['label', 'points' => [], 'unit' => ''])
<div {{ $attributes->class(['md-kit-sparkline']) }}>
    @if (count($points) >= 2)<div data-md-chart data-md-chart-compact="true" data-md-chart-name="{{ $label }}" data-md-chart-unit="{{ $unit }}" data-md-chart-points="{{ json_encode($points, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}" aria-label="{{ $label }}"></div>
    <details><summary>{{ $label }} — данные</summary><ul>@foreach ($points as $point)<li>{{ date('d.m.Y H:i', (int) ($point['t'] / 1000)) }}: {{ $point['v'] }} {{ $unit }}</li>@endforeach</ul></details>
    @else<span class="md-table__muted">Недостаточно данных</span>@endif
</div>
