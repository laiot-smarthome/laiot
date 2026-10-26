@props([
    'tone' => 'tip',
    'title' => null,
    'text' => null,
    'eyebrow' => null,
    'icon' => null,
    'points' => [],
    'steps' => [],
    'actions' => null,
    'link' => null,
    'linkLabel' => 'Подробнее',
    'dismissible' => false,
    'compact' => false,
    'id' => null,
])

@php
    $toneIcons = [
        'tip' => 'bulb',
        'info' => 'info-circle',
        'success' => 'circle-check',
        'warning' => 'alert-triangle',
        'danger' => 'alert-circle',
    ];
    $labels = [
        'tip' => 'Подсказка',
        'info' => 'Как это работает',
        'success' => 'Готово',
        'warning' => 'Обратите внимание',
        'danger' => 'Важно',
    ];
    $tone = array_key_exists($tone, $toneIcons) ? $tone : 'tip';
    $icon = $icon ?? $toneIcons[$tone];
    $eyebrow = $eyebrow ?? $labels[$tone];
    $points = array_values(array_filter(array_map('strval', $points), static fn (string $point): bool => $point !== ''));
    $steps = array_values(array_filter(array_map('strval', $steps), static fn (string $step): bool => $step !== ''));
@endphp

<aside {{ $attributes->class(['md-hint', 'md-hint--' . $tone, 'md-hint--compact' => $compact]) }}
       @if ($dismissible) x-data="{show: true}" x-show="show" @endif
       role="note"
       @if ($id !== null) id="{{ $id }}" @endif>
    <span class="md-hint__icon"><x-md.icon :name="$icon" /></span>
    <div class="md-hint__body">
        @if ($eyebrow !== null)<div class="md-hint__eyebrow">{{ $eyebrow }}</div>@endif
        @if ($title !== null)<div class="md-hint__title">{{ $title }}</div>@endif
        @if ($text !== null)<p class="md-hint__text">{{ $text }}</p>@endif
        @if (trim((string)$slot) !== '')<div class="md-hint__content">{{ $slot }}</div>@endif
        @if ($points !== [])
            <ul class="md-hint__points">
                @foreach ($points as $point)<li>{{ $point }}</li>@endforeach
            </ul>
        @endif
        @if ($steps !== [])
            <ol class="md-hint__steps">
                @foreach ($steps as $step)<li>{{ $step }}</li>@endforeach
            </ol>
        @endif
        @if ($actions !== null || $link !== null)
            <div class="md-hint__actions">
                {!! $actions !!}
                @if ($link !== null)
                    <x-md.button variant="ghost" size="sm" :href="$link" icon-after="arrow-right">
                        {{ $linkLabel }}
                    </x-md.button>
                @endif
            </div>
        @endif
    </div>
    @if ($dismissible)
        <button type="button" class="md-hint__close" @click="show = false" aria-label="Скрыть подсказку">
            <x-md.icon name="x" />
        </button>
    @endif
</aside>
