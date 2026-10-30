@props(['value' => null, 'snapshot' => null, 'compact' => false])
@php
    $node = $snapshot ?? (new \LaIOT\Services\Xray\ValueSnapshot())->capture($value);
    $preview = [];
    foreach (array_slice($node['children'] ?? [], 0, 3) as $child) {
        $preview[] = $child['key'] . ': ' . (isset($child['node']['children']) ? $child['node']['type'] . '(' . $child['node']['count'] . ')' : var_export($child['node']['value'], true));
    }
    $summary = mb_substr(isset($node['children']) ? ($preview !== [] ? implode(' · ', $preview) : $node['type'] . '(0)') : var_export($node['value'] ?? null, true), 0, 180);
@endphp
@if ($compact)
    <details {{ $attributes->class(['md-data-disclosure']) }} data-md-inspect>
        <summary title="{{ $summary }}"><span class="md-data-summary">{{ $summary }}</span></summary>
        <div class="md-data-view">@include('components.md.data-node', ['node' => $node, 'key' => null, 'depth' => 0])</div>
    </details>
@else
    <div {{ $attributes->class(['md-data-view']) }}>@include('components.md.data-node', ['node' => $node, 'key' => null, 'depth' => 0])</div>
@endif
