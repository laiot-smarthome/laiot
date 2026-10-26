@props([
    'items' => [],
    'groups' => [],
    'title' => null,
    'subtitle' => null,
    'eyebrow' => null,
    'icon' => 'history',
    'actions' => null,
    'footer' => null,
    'limit' => 0,
    'compact' => false,
    'bare' => false,
    'emptyTitle' => 'Пока нет событий',
    'emptyText' => 'Здесь появится история действий, когда что-то произойдёт.',
    'id' => null,
])

@php
    $toneIcons = [
        'primary' => 'activity',
        'success' => 'circle-check',
        'warning' => 'alert-triangle',
        'danger' => 'alert-circle',
        'info' => 'info-circle',
        'neutral' => 'point',
    ];
    $tones = array_keys($toneIcons);

    $normalize = static function (array $item) use ($tones): array {
        $tone = isset($item['tone']) && in_array($item['tone'], $tones, true) ? $item['tone'] : 'neutral';

        return [
            'title' => (string)($item['title'] ?? ''),
            'text' => (string)($item['text'] ?? ''),
            'time' => (string)($item['time'] ?? ''),
            'datetime' => (string)($item['datetime'] ?? ''),
            'date' => (string)($item['date'] ?? ''),
            'icon' => (string)($item['icon'] ?? ''),
            'tone' => $tone,
            'badge' => (string)($item['badge'] ?? ''),
            'badge_tone' => (string)($item['badge_tone'] ?? 'outline'),
            'actor' => (string)($item['actor'] ?? ''),
            'meta' => is_array($item['meta'] ?? null) ? $item['meta'] : [],
            'actions' => (string)($item['actions'] ?? ''),
            'href' => (string)($item['href'] ?? ''),
            'unread' => (bool)($item['unread'] ?? false),
        ];
    };

    $normalizedGroups = [];
    if ($groups !== []) {
        foreach ($groups as $group) {
            $groupItems = array_map($normalize, is_array($group['items'] ?? null) ? $group['items'] : []);
            if ($groupItems !== []) {
                $normalizedGroups[] = [
                    'label' => (string)($group['label'] ?? ''),
                    'items' => $groupItems,
                ];
            }
        }
    } else {
        $currentLabel = null;
        $currentItems = [];
        foreach ($items as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }
            $item = $normalize($rawItem);
            $date = trim($item['date']);
            if ($currentLabel === null) {
                $currentLabel = $date;
            } elseif ($date !== $currentLabel) {
                if ($currentItems !== []) {
                    $normalizedGroups[] = ['label' => $currentLabel, 'items' => $currentItems];
                }
                $currentLabel = $date;
                $currentItems = [];
            }
            $currentItems[] = $item;
        }
        if ($currentItems !== []) {
            $normalizedGroups[] = ['label' => $currentLabel, 'items' => $currentItems];
        }
    }

    $total = 0;
    foreach ($normalizedGroups as $group) {
        $total += count($group['items']);
    }

    $limit = max(0, (int)$limit);
    if ($limit > 0) {
        $shown = 0;
        foreach ($normalizedGroups as $index => $group) {
            $take = max(0, $limit - $shown);
            $normalizedGroups[$index]['items'] = array_slice($group['items'], 0, $take);
            $shown += count($normalizedGroups[$index]['items']);
        }
        $normalizedGroups = array_values(array_filter($normalizedGroups, static fn (array $group): bool => $group['items'] !== []));
    }

    $hasHead = $title !== null || $subtitle !== null || $eyebrow !== null || $actions !== null;
@endphp

@if ($bare)
    <div @class(['md-activity', 'md-activity--compact' => $compact])>
        @foreach ($normalizedGroups as $group)
            <div class="md-activity__group">
                @if ($group['label'] !== '')<div class="md-activity__date">{{ $group['label'] }}</div>@endif
                <ol class="md-activity__list">
                    @foreach ($group['items'] as $item)
                        <li @class(['md-activity__item', 'md-activity__item--' . $item['tone'], 'is-unread' => $item['unread']])>
                            <span class="md-activity__rail" aria-hidden="true">
                                <span class="md-activity__dot"><x-md.icon :name="$item['icon'] !== '' ? $item['icon'] : ($toneIcons[$item['tone']] ?? 'point')" /></span>
                            </span>
                            <div class="md-activity__content">
                                <div class="md-activity__head">
                                    <div class="md-activity__heading">
                                        @if ($item['unread'])<span class="md-activity__unread" aria-label="Новое событие"></span>@endif
                                        <span class="md-activity__title">
                                            @if ($item['href'] !== '')
                                                <a class="md-activity__title-link" href="{{ $item['href'] }}">{{ $item['title'] }}</a>
                                            @else
                                                {{ $item['title'] }}
                                            @endif
                                        </span>
                                    </div>
                                    @if ($item['time'] !== '')
                                        <time class="md-activity__time" @if ($item['datetime'] !== '') datetime="{{ $item['datetime'] }}" title="{{ $item['datetime'] }}" @endif>{{ $item['time'] }}</time>
                                    @endif
                                </div>
                                @if ($item['text'] !== '')<p class="md-activity__text">{{ $item['text'] }}</p>@endif
                                @if ($item['actor'] !== '' || $item['badge'] !== '' || $item['actions'] !== '' || $item['meta'] !== [])
                                    <div class="md-activity__meta">
                                        @if ($item['actor'] !== '')
                                            <span class="md-activity__chip"><x-md.icon name="user" />{{ $item['actor'] }}</span>
                                        @endif
                                        @foreach ($item['meta'] as $meta)
                                            @if (is_array($meta) && (string)($meta['text'] ?? '') !== '')
                                                <span class="md-activity__chip">
                                                    @if (!empty($meta['icon']))<x-md.icon :name="$meta['icon']" />@endif
                                                    @if (!empty($meta['href']))
                                                        <a href="{{ $meta['href'] }}">{{ $meta['text'] }}</a>
                                                    @else
                                                        {{ $meta['text'] }}
                                                    @endif
                                                </span>
                                            @elseif (!is_array($meta) && (string)$meta !== '')
                                                <span class="md-activity__chip">{{ $meta }}</span>
                                            @endif
                                        @endforeach
                                        @if ($item['badge'] !== '')
                                            <x-md.badge :tone="$item['badge_tone']">{{ $item['badge'] }}</x-md.badge>
                                        @endif
                                        @if ($item['actions'] !== '')
                                            <div class="md-activity__actions">{!! $item['actions'] !!}</div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endforeach
    </div>
@else
    <section {{ $attributes->class(['md-card', 'md-activity-card', 'md-card--compact' => $compact]) }} @if ($id !== null) id="{{ $id }}" @endif>
        @if ($hasHead)
            <header class="md-card__head">
                <div class="md-card__titles">
                    @if ($eyebrow !== null)<div class="md-card__eyebrow">{{ $eyebrow }}</div>@endif
                    @if ($title !== null)
                        <h3 class="md-card__title">
                            <x-md.icon :name="$icon" class="md-card__title-icon" />
                            {{ $title }}
                        </h3>
                    @endif
                    @if ($subtitle !== null)<p class="md-card__subtitle">{{ $subtitle }}</p>@endif
                </div>
                @if ($actions !== null)<div class="md-card__actions">{!! $actions !!}</div>@endif
            </header>
        @endif

        <div class="md-card__body">
            @if ($total === 0)
                <x-md.empty-state icon="history" :title="$emptyTitle" :text="$emptyText" compact />
            @else
                <div @class(['md-activity', 'md-activity--compact' => $compact])>
                    @foreach ($normalizedGroups as $group)
                        <div class="md-activity__group">
                            @if ($group['label'] !== '')<div class="md-activity__date">{{ $group['label'] }}</div>@endif
                            <ol class="md-activity__list">
                                @foreach ($group['items'] as $item)
                                    <li @class(['md-activity__item', 'md-activity__item--' . $item['tone'], 'is-unread' => $item['unread']])>
                                        <span class="md-activity__rail" aria-hidden="true">
                                            <span class="md-activity__dot"><x-md.icon :name="$item['icon'] !== '' ? $item['icon'] : ($toneIcons[$item['tone']] ?? 'point')" /></span>
                                        </span>
                                        <div class="md-activity__content">
                                            <div class="md-activity__head">
                                                <div class="md-activity__heading">
                                                    @if ($item['unread'])<span class="md-activity__unread" aria-label="Новое событие"></span>@endif
                                                    <span class="md-activity__title">
                                                        @if ($item['href'] !== '')
                                                            <a class="md-activity__title-link" href="{{ $item['href'] }}">{{ $item['title'] }}</a>
                                                        @else
                                                            {{ $item['title'] }}
                                                        @endif
                                                    </span>
                                                </div>
                                                @if ($item['time'] !== '')
                                                    <time class="md-activity__time" @if ($item['datetime'] !== '') datetime="{{ $item['datetime'] }}" title="{{ $item['datetime'] }}" @endif>{{ $item['time'] }}</time>
                                                @endif
                                            </div>
                                            @if ($item['text'] !== '')<p class="md-activity__text">{{ $item['text'] }}</p>@endif
                                            @if ($item['actor'] !== '' || $item['badge'] !== '' || $item['actions'] !== '' || $item['meta'] !== [])
                                                <div class="md-activity__meta">
                                                    @if ($item['actor'] !== '')
                                                        <span class="md-activity__chip"><x-md.icon name="user" />{{ $item['actor'] }}</span>
                                                    @endif
                                                    @foreach ($item['meta'] as $meta)
                                                        @if (is_array($meta) && (string)($meta['text'] ?? '') !== '')
                                                            <span class="md-activity__chip">
                                                                @if (!empty($meta['icon']))<x-md.icon :name="$meta['icon']" />@endif
                                                                @if (!empty($meta['href']))
                                                                    <a href="{{ $meta['href'] }}">{{ $meta['text'] }}</a>
                                                                @else
                                                                    {{ $meta['text'] }}
                                                                @endif
                                                            </span>
                                                        @elseif (!is_array($meta) && (string)$meta !== '')
                                                            <span class="md-activity__chip">{{ $meta }}</span>
                                                        @endif
                                                    @endforeach
                                                    @if ($item['badge'] !== '')
                                                        <x-md.badge :tone="$item['badge_tone']">{{ $item['badge'] }}</x-md.badge>
                                                    @endif
                                                    @if ($item['actions'] !== '')
                                                        <div class="md-activity__actions">{!! $item['actions'] !!}</div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($footer !== null)
            <footer class="md-card__footer">{!! $footer !!}</footer>
        @endif
    </section>
@endif
