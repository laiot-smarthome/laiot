@props(['items' => [], 'label' => 'Навигационная цепочка'])
@if ($items !== [])
    <nav {{ $attributes }} aria-label="{{ $label }}">
        <ol class="breadcrumb md-breadcrumb">
            @foreach ($items as $item)
                @php
                    $href = $item['href'] ?? null;
                    $href = is_string($href) && (str_starts_with($href, '#') || (str_starts_with($href, '/') && !str_starts_with($href, '//'))) ? $href : null;
                @endphp
                <li @class(['breadcrumb-item', 'active' => $loop->last]) @if ($loop->last) aria-current="page" @endif>
                    @if (!$loop->last && $href !== null)<a href="{{ $href }}">{{ $item['label'] ?? '' }}</a>@else{{ $item['label'] ?? '' }}@endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
