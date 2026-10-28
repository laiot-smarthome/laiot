@props(['pose' => 'hello'])

@php
    $poses = ['drink', 'error', 'find', 'nothing', 'hello', 'hide', 'idea', 'joy', 'love_home', 'love', 'message', 'music', 'quest', 'read', 'sleep', 'success', 'todo', 'work', 'wow'];
    if (!in_array($pose, $poses, true)) {
        throw new \InvalidArgumentException('Неизвестная поза маскота.');
    }
@endphp

<img {{ $attributes->class(['md-mascot']) }} src="/assets/img/maskot/{{ $pose }}.png" alt="" width="250" height="250" decoding="async">
