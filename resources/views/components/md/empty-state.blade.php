@props([
    'icon' => 'inbox',
    'title' => 'Нет данных',
    'text' => null,
    'action' => null,
    'compact' => false,
])

<div {{ $attributes->class(['md-empty', 'md-empty--compact' => $compact]) }}>
    <span class="md-empty__icon"><x-md.icon :name="$icon" /></span>
    <div class="md-empty__title">{{ $title }}</div>
    @if ($text !== null)<p class="md-empty__text">{{ $text }}</p>@endif
    @if ($action !== null)<div class="md-empty__actions">{!! $action !!}</div>@endif
</div>
