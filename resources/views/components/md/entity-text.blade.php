@props(['text' => ''])
@php
    $segments = [];
    $offset = 0;
    $types = ['object', 'class', 'property', 'method', 'script', 'module', 'room'];
    preg_match_all('/\[\[([a-z]+):([^\]|\r\n]+)(?:\|([^\]|\r\n]*))?(?:\|([^\]\r\n]*))?\]\]/iu', (string) $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($matches as $match) {
        $position = $match[0][1];
        $segments[] = ['text' => substr((string) $text, $offset, $position - $offset)];
        $type = strtolower($match[1][0]);
        if (in_array($type, $types, true)) {
            $segments[] = ['type' => $type, 'name' => trim($match[2][0]), 'label' => $match[3][0] ?? null, 'href' => $match[4][0] ?? null];
        } else {
            $segments[] = ['text' => $match[0][0]];
        }
        $offset = $position + strlen($match[0][0]);
    }
    $segments[] = ['text' => substr((string) $text, $offset)];
@endphp
<span {{ $attributes->class(['md-kit-entity-text']) }}>
    @foreach ($segments as $segment)
        @if (isset($segment['type']))
            <x-md.entity :type="$segment['type']" :name="$segment['name']" :label="$segment['label'] ?: null" :href="$segment['href']" />
        @else
            {{ $segment['text'] }}
        @endif
    @endforeach
</span>
