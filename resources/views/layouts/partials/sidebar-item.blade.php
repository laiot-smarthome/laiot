@php($badge = $item['badge'] ?? null)
@php($isActive = ($active ?? '') === ($item['id'] ?? ''))
<li @class(['md-nav__item', 'active' => $isActive])>
    <a class="md-nav__link" href="{{ $item['href'] ?? '#' }}" title="{{ $item['label'] ?? '' }}" data-md-label="{{ $item['label'] ?? '' }}"
       @if ($isActive) aria-current="page" @endif>
        <span class="md-nav__icon" aria-hidden="true"><i class="ti {{ $item['icon'] ?? 'ti-point' }}"></i></span>
        <span class="md-nav__label">{{ $item['label'] ?? '' }}</span>
        @if ($badge)
            <span @class(['md-nav__badge', 'md-nav__badge--' . ($badge['tone'] ?? 'info')])
                  data-md-attention="{{ $item['id'] }}"
                  data-md-count="{{ $badge['count'] ?? 0 }}"
                  data-md-tone="{{ $badge['tone'] ?? 'info' }}"
                  title="{{ $badge['title'] ?? '' }}"
                  @if (empty($badge['count'])) hidden @endif>{{ $badge['label'] ?? '0' }}</span>
        @endif
    </a>
</li>
