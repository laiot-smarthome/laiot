@props(['name'])

<i {{ $attributes->class(['ti', 'ti-' . $name]) }} aria-hidden="true"></i>
