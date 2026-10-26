@props([
    'title' => null,
    'subtitle' => null,
    'eyebrow' => null,
    'icon' => null,
    'actions' => null,
    'footer' => null,
    'flush' => false,
    'compact' => false,
    'interactive' => false,
    'headingLevel' => 3,
])

@php
    $headingLevel = in_array((int)$headingLevel, [1, 2, 3, 4], true) ? (int)$headingLevel : 3;
    $hasHead = $title !== null || $subtitle !== null || $eyebrow !== null || $actions !== null;
    $classes = array_filter([
        'md-card',
        $compact ? 'md-card--compact' : null,
        $interactive ? 'md-card--interactive' : null,
    ]);
@endphp

<section {{ $attributes->class($classes) }}>
    @if ($hasHead)
        <header class="md-card__head">
            <div class="md-card__titles">
                @if ($eyebrow !== null)<div class="md-card__eyebrow">{{ $eyebrow }}</div>@endif
                @if ($title !== null)
                    <h{{ $headingLevel }} class="md-card__title">
                        @if ($icon)<x-md.icon :name="$icon" class="md-card__title-icon" />@endif
                        {{ $title }}
                    </h{{ $headingLevel }}>
                @endif
                @if ($subtitle !== null)<p class="md-card__subtitle">{{ $subtitle }}</p>@endif
            </div>
            @if ($actions !== null)<div class="md-card__actions">{!! $actions !!}</div>@endif
        </header>
    @endif

    <div @class(['md-card__body', 'md-card__body--flush' => $flush])>{{ $slot }}</div>

    @if ($footer !== null)
        <footer class="md-card__footer">{!! $footer !!}</footer>
    @endif
</section>
