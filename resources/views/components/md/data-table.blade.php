@props(['columns' => [], 'rows' => [], 'label' => 'Таблица', 'pageSize' => 10])

<div {{ $attributes->class(['md-table-wrap']) }} x-data="mdDataTable(@js($columns), @js($rows), @js(max(1, (int) $pageSize)))">
    <div class="md-table__toolbar" x-cloak x-show="ready">
        <input class="md-input" type="search" aria-label="Поиск по таблице" placeholder="Поиск по таблице" x-model="query" @input.debounce.200ms="search()">
    </div>
    <div x-ref="grid" aria-label="{{ $label }}"></div>
    <div class="md-table__scroll" x-show="!ready">
        <table class="md-table" aria-label="{{ $label }}"><thead><tr>@foreach ($columns as $column)<th scope="col">{{ $column['label'] }}</th>@endforeach</tr></thead>
            <tbody>@foreach ($rows as $row)<tr>@foreach ($columns as $column)<td>{{ $row[$column['key']] ?? '' }}</td>@endforeach</tr>@endforeach</tbody>
        </table>
    </div>
</div>
