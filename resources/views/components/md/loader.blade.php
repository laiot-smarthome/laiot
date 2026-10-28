@props(['label' => 'Загрузка', 'size' => 'md', 'block' => false])

@php($size = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md')

<span {{ $attributes->class(['md-loader', 'md-loader--' . $size, 'md-loader--block' => $block]) }} role="status">
    <span class="md-spinner" aria-hidden="true"></span>
    <span class="visually-hidden">{{ $label }}</span>
</span>
