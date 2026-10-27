@props(['name', 'type' => 'object', 'href' => null, 'label' => null])
@php
    $types = ['object' => ['Объект', 'box'], 'class' => ['Класс', 'hierarchy'], 'property' => ['Свойство', 'variable'], 'method' => ['Метод', 'function'], 'script' => ['Сценарий', 'file-code'], 'module' => ['Модуль', 'puzzle'], 'room' => ['Комната', 'home']];
    [$caption, $icon] = $types[$type] ?? ['Сущность', 'tag'];
    $safeHref = is_string($href) && (str_starts_with($href, '#') || (str_starts_with($href, '/') && !str_starts_with($href, '//'))) ? $href : null;
@endphp
@if ($safeHref !== null)<a href="{{ $safeHref }}" {{ $attributes->class(['md-entity', 'md-entity--' . $type]) }} title="{{ $caption }} · {{ $name }}"><x-md.icon :name="$icon" /><span>{{ $caption }}: {{ $label ?? $name }}</span></a>
@else<span {{ $attributes->class(['md-entity', 'md-entity--' . $type]) }} title="{{ $caption }} · {{ $name }}"><x-md.icon :name="$icon" /><span>{{ $caption }}: {{ $label ?? $name }}</span></span>@endif
