@props(['title', 'subtitle' => null, 'points' => [], 'unit' => ''])
<x-md.card :title="$title" :subtitle="$subtitle" {{ $attributes }}>
    @if (count($points) >= 2)
        <div class="md-history-chart" data-md-chart data-md-chart-name="{{ $title }}" data-md-chart-unit="{{ $unit }}" data-md-chart-points="{{ json_encode($points, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}" aria-label="{{ $title }}"></div>
        <details><summary>Данные графика</summary><div class="md-table__scroll"><table class="md-table"><thead><tr><th scope="col">Время</th><th scope="col">Значение</th></tr></thead><tbody>@foreach ($points as $point)<tr><td>{{ date('d.m.Y H:i', (int) ($point['t'] / 1000)) }}</td><td>{{ $point['v'] }} {{ $unit }}</td></tr>@endforeach</tbody></table></div></details>
    @else
        <x-md.empty-state title="Недостаточно данных" text="Для графика нужно минимум две точки." compact />
    @endif
</x-md.card>
