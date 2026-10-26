@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
    'actions' => null,
    'level' => 2,
])

@php
    $level = in_array((int)$level, [1, 2, 3], true) ? (int)$level : 2;
@endphp

<header {{ $attributes->class(['md-section-head']) }}>
    <div class="md-section-head__text">
        @if ($eyebrow !== null)<div class="md-section-head__eyebrow">{{ $eyebrow }}</div>@endif
        @if ($title !== null)
            <h{{ $level }} class="md-section-head__title">{{ $title }}</h{{ $level }}>
        @endif
        @if ($description !== null)<p class="md-section-head__description">{{ $description }}</p>@endif
    </div>
    @if ($actions !== null)<div class="md-section-head__actions">{!! $actions !!}</div>@endif
</header>
