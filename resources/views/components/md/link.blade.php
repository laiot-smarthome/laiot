@props([
    'href' => null,
    'variant' => 'primary',
    'icon' => null,
    'iconAfter' => null,
    'disabled' => false,
])

@php
    // Ссылки панели не подчёркиваются. Вариант `hint` — исключение: пунктир
    // показывает, что рядом есть пояснение или подсказка.
    $variants = ['primary', 'muted', 'subtle', 'danger', 'hint', 'action'];
    $variant = in_array($variant, $variants, true) ? $variant : 'primary';

    $href = is_string($href) ? trim($href) : null;
    $isExternal = $href !== null && preg_match('~^https?://~i', $href) === 1;

    $safeHref = null;
    if ($href !== null && (
        str_starts_with($href, '#')
        || (str_starts_with($href, '/') && !str_starts_with($href, '//'))
        || $isExternal
    )) {
        $safeHref = $href;
    }

    $iconAfter = $iconAfter ?? ($isExternal ? 'external-link' : null);
    $classes = array_filter([
        'md-link',
        'md-link--' . $variant,
        ($disabled || $safeHref === null) ? 'is-disabled' : null,
    ]);
@endphp

@if ($safeHref !== null && !$disabled)
    <a href="{{ $safeHref }}"
       @if ($isExternal) target="_blank" rel="noopener noreferrer" @endif
       {{ $attributes->class($classes) }}>
        @if ($icon)<x-md.icon :name="$icon" class="md-link__icon" />@endif
        <span class="md-link__label">{{ $slot }}</span>
        @if ($iconAfter)<x-md.icon :name="$iconAfter" class="md-link__icon md-link__icon--after" />@endif
    </a>
@else
    <span {{ $attributes->class($classes) }} aria-disabled="true">
        @if ($icon)<x-md.icon :name="$icon" class="md-link__icon" />@endif
        <span class="md-link__label">{{ $slot }}</span>
        @if ($iconAfter)<x-md.icon :name="$iconAfter" class="md-link__icon md-link__icon--after" />@endif
    </span>
@endif
