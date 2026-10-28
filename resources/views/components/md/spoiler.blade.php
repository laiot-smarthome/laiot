@props([
    'title' => '',
    'description' => null,
    'open' => false,
    'icon' => null,
    'badge' => null,
    'badgeTone' => 'neutral',
    'variant' => 'subtle',
    'id' => null,
])

<details {{ $attributes->class([
    'md-spoiler',
    'md-spoiler--' . $variant,
]) }} @if ($open) open @endif @if ($id !== null) id="{{ $id }}" @endif>
    <summary class="md-spoiler__summary">
        @if ($icon)
            <x-md.icon :name="$icon" class="md-spoiler__icon" />
        @endif
        <span class="md-spoiler__header">
            <span class="md-spoiler__title">{{ $title }}</span>
            @if ($description !== null)
                <span class="md-spoiler__description">{{ $description }}</span>
            @endif
        </span>
        @if ($badge !== null)
            <x-md.badge :tone="$badgeTone" class="md-spoiler__badge">{{ $badge }}</x-md.badge>
        @endif
        <x-md.icon name="chevron-down" class="md-spoiler__chevron" />
    </summary>
    <div class="md-spoiler__body">
        {{ $slot }}
    </div>
</details>
