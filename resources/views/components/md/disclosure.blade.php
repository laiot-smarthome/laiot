@props([
    'title' => '',
    'description' => null,
    'badge' => null,
    'badgeTone' => 'neutral',
    'open' => false,
    'id' => null,
])

<details {{ $attributes->class(['md-disclosure']) }} @if ($open) open @endif @if ($id !== null) id="{{ $id }}" @endif>
    <summary class="md-disclosure__summary">
        @if ($badge !== null)
            <x-md.badge :tone="$badgeTone">{{ $badge }}</x-md.badge>
        @endif
        <span class="md-disclosure__heading">
            <span class="md-disclosure__title">{{ $title }}</span>
            @if ($description !== null)<span class="md-disclosure__description">{{ $description }}</span>@endif
        </span>
        <x-md.icon name="chevron-down" class="md-disclosure__chevron" />
    </summary>
    <div class="md-disclosure__body">{{ $slot }}</div>
</details>
