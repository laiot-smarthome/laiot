@props([
    'label' => null,
    'variant' => null,
])

@php
    // Дерево классов использует тот же каркас, но другую таблицу-обёртку.
    $tableClass = $variant === 'tree' ? 'md-table md-tree-table' : 'md-table';
@endphp

{{--
    Единый каркас таблицы раздела: тулбар, прокручиваемая сетка, пустое
    состояние и футер. Страницы передают только содержимое слотов, поэтому
    отступы, границы и мобильный скролл не расходятся между разделами.
--}}
<div {{ $attributes->class(['md-table-wrap']) }} @if ($label !== null) aria-label="{{ $label }}" @endif>
    @if (trim((string)($toolbar ?? '')) !== '')
        <div class="md-table__toolbar">{{ $toolbar }}</div>
    @endif

    @if (trim((string)($head ?? '')) !== '')
        <div class="md-table__scroll">
            <table class="{{ $tableClass }}">
                <thead>
                <tr>{{ $head }}</tr>
                </thead>
                <tbody>{{ $body ?? '' }}</tbody>
            </table>
        </div>
    @else
        {{ $empty ?? '' }}
    @endif

    @if (trim((string)($footer ?? '')) !== '')
        <div class="md-table__footer">{{ $footer }}</div>
    @endif
</div>
