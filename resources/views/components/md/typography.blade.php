@props(['variant' => 'body', 'as' => null])
@php
    $defaults = ['display' => 'h1', 'h1' => 'h1', 'h2' => 'h2', 'h3' => 'h3', 'body' => 'p', 'small' => 'p', 'caption' => 'span', 'mono' => 'code'];
    $variant = array_key_exists($variant, $defaults) ? $variant : 'body';
    $tag = in_array($as, ['h1', 'h2', 'h3', 'h4', 'p', 'span', 'div', 'code'], true) ? $as : $defaults[$variant];
@endphp
<{{ $tag }} {{ $attributes->class(['md-ui-type--' . $variant]) }}>{{ $slot }}</{{ $tag }}>
