@props([
    'type' => 'info',
    'title' => null,
    'text' => null,
    'actions' => null,
    'dismissible' => false,
    'icon' => null,
])

@php
    $types = ['info', 'success', 'warning', 'danger'];
    $type = in_array($type, $types, true) ? $type : 'info';
    $icons = [
        'info' => 'info-circle',
        'success' => 'circle-check',
        'warning' => 'alert-triangle',
        'danger' => 'circle-x',
    ];
    $icon = $icon ?? $icons[$type];
@endphp

<div {{ $attributes->class(['md-alert', 'md-alert--' . $type]) }}
     @if ($dismissible) x-data="{show: true}" x-show="show" @endif
     role="{{ $type === 'danger' ? 'alert' : 'status' }}">
    <span class="md-alert__icon"><x-md.icon :name="$icon" /></span>
    <div class="md-alert__body">
        @if ($title !== null)<div class="md-alert__title">{{ $title }}</div>@endif
        @if ($text !== null)<div class="md-alert__text">{{ $text }}</div>@endif
        @if ($actions !== null)<div class="md-alert__actions">{!! $actions !!}</div>@endif
    </div>
    @if ($dismissible)
        <button type="button" class="md-alert__close" @click="show = false" aria-label="Закрыть">
            <x-md.icon name="x-lg" />
        </button>
    @endif
</div>
