@props([
    'eyebrow' => null,
    'icon' => null,
    'title' => null,
    'lede' => null,
    'meta' => [],
])

@php
    // Заголовок раздела — единственный h1 на странице. Он отделён от контента
    // линией, как в alpha-панели: так список и карточки не «плывут» к шапке.
    $meta = array_values(array_filter((array)$meta, static fn (mixed $item): bool => is_array($item) && $item !== []));
@endphp

<header {{ $attributes->class(['md-ui-hero']) }}>
    <div>
        @if ($eyebrow !== null)
            <div class="md-ui-hero__eyebrow">
                @if ($icon !== null)<x-md.icon :name="$icon" />@endif
                <span>{{ $eyebrow }}</span>
            </div>
        @endif

        <h1 class="md-ui-hero__title">{{ $title ?? $slot }}</h1>

        @if ($lede !== null)
            <p class="md-ui-hero__lede">{{ $lede }}</p>
        @endif

        @if ($meta !== [])
            <div class="md-ui-hero__meta">
                @foreach ($meta as $item)
                    <span class="md-ui-hero__meta-item">
                        @if (!empty($item['icon']))<x-md.icon :name="$item['icon']" />@endif
                        @if (!empty($item['label']))<span>{{ $item['label'] }}</span>@endif
                        @if (isset($item['value']) && $item['value'] !== '')<strong>{{ $item['value'] }}</strong>@endif
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    @if (trim((string)($actions ?? '')) !== '')
        <div class="md-ui-hero__actions">{{ $actions }}</div>
    @endif
</header>
