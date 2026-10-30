@props(['old' => null, 'new' => null])
@php
    $display = static fn ($value): string => is_string($value) ? $value : (is_scalar($value) || $value === null ? var_export($value, true) : get_debug_type($value));
    $before = $display($old);
    $after = $display($new);
@endphp
<span {{ $attributes->class(['md-value-change']) }}>
    <span class="md-value-change__old" title="{{ mb_substr($before, 0, 4096) }}"><span class="visually-hidden">Было: </span>{{ $before === '' ? '∅' : $before }}</span>
    <x-md.icon name="arrow-right" />
    <span class="md-value-change__new" title="{{ mb_substr($after, 0, 4096) }}"><span class="visually-hidden">Стало: </span>{{ $after === '' ? '∅' : $after }}</span>
</span>
