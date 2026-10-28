@props(['label' => 'Группа кнопок'])
<div {{ $attributes->class(['md-btn-group']) }} role="group" aria-label="{{ $label }}">{{ $slot }}</div>
